<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Models\WalletHistory;
use App\Services\PaymentService\PayPalService;
use Illuminate\Http\Request;

class PayPalController extends PaymentBaseController
{
    public function __construct(private PayPalService $service)
    {
        parent::__construct($service);
    }

    /**
     * PayPal's intent=CAPTURE flow does not auto-capture on approval - a
     * separate server-side capture call is required, and only its own
     * result decides paid vs rejected. CHECKOUT.ORDER.APPROVED therefore
     * triggers that call rather than being trusted as "paid" on its own.
     * PAYMENT.CAPTURE.COMPLETED/DENIED are handled too, as a safety net
     * for a capture confirmation that arrives independently of the
     * synchronous call below (e.g. this webhook's own response to PayPal
     * was lost after the capture had already gone through) -
     * BaseService::afterHook() is idempotent (see its own
     * already-updated guard), so handling the same completion twice here
     * is safe.
     *
     * @param Request $request
     * @return array
     */
    public function paymentWebHook(Request $request): array
    {
        $eventType = $request->input('event_type');

        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            $orderId = $request->input('resource.id');
            $capture = $this->service->captureOrder($orderId);

            // ALREADY_CAPTURED means a duplicate/retried delivery of this
            // same event - the original capture attempt already reported
            // its own real result, so this one must not overwrite it
            // (in particular, must never turn an already-paid transaction
            // into a false rejection).
            if ($capture['status'] === 'ALREADY_CAPTURED') {
                return ['status' => false, 'message' => 'already captured'];
            }

            $status = match ($capture['status']) {
                'COMPLETED'          => WalletHistory::PAID,
                'DECLINED', 'FAILED' => WalletHistory::REJECTED,
                default              => 'progress',
            };

            return $this->service->afterHook($orderId, $status);
        }

        if (in_array($eventType, ['PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED'])) {
            $status  = $eventType === 'PAYMENT.CAPTURE.COMPLETED' ? WalletHistory::PAID : WalletHistory::REJECTED;
            $orderId = $request->input('resource.supplementary_data.related_ids.order_id');

            return $this->service->afterHook($orderId, $status);
        }

        return [
            'status'  => false,
            'message' => 'unhandled event type',
        ];
    }

}
