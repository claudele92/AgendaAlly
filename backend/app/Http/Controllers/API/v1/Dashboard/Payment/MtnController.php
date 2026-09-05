<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Helpers\ResponseError;
use App\Models\PaymentProcess;
use App\Models\Transaction;
use App\Services\PaymentService\MtnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Throwable;

class MtnController extends PaymentBaseController
{
    public function __construct(private MtnService $service)
    {
        parent::__construct($service);
    }

    /**
     * MTN's own webhook shape: `externalId` is the reference id we
     * generated at request time (see MtnService::processTransaction),
     * `status` is SUCCESSFUL/FAILED/PENDING. Delivery isn't guaranteed —
     * see the mtn:reconcile-pending-payments scheduled command for the
     * backstop when it doesn't arrive.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentWebHook(Request $request): JsonResponse
    {
        try {
            Log::info('mtn webhook', $request->all());

            $status = match ($request->input('status')) {
                'FAILED'     => Transaction::STATUS_CANCELED,
                'SUCCESSFUL' => Transaction::STATUS_PAID,
                default      => Transaction::STATUS_PROGRESS,
            };

            $token = $request->input('externalId');

            $this->service->afterHook($token, $status);

        } catch (Throwable $e) {
            $this->error($e);
        }

        return $this->successResponse(__('errors.' . ResponseError::NO_ERROR));
    }

    /**
     * Lets the client (or an admin) actively poll MTN for a pending
     * request-to-pay's status, rather than only waiting on the webhook.
     *
     * @param string $referenceId
     * @return JsonResponse
     */
    public function checkStatus(string $referenceId): JsonResponse
    {
        try {
            $paymentProcess = PaymentProcess::find($referenceId);

            if (!$paymentProcess) {
                return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
            }

            $config = $this->service->resolveGatewayConfig($paymentProcess->data, data_get($paymentProcess->data, 'payment_id'));

            if (!$config) {
                return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
            }

            $result = $this->service->checkStatus($config, $referenceId);

            $status = match ($result['status'] ?? null) {
                'FAILED'     => Transaction::STATUS_CANCELED,
                'SUCCESSFUL' => Transaction::STATUS_PAID,
                default      => Transaction::STATUS_PROGRESS,
            };

            $this->service->afterHook($referenceId, $status);

            return $this->successResponse(__('errors.' . ResponseError::NO_ERROR), $result);
        } catch (Throwable $e) {
            $this->error($e);

            return $this->onErrorResponse(['message' => $e->getMessage()]);
        }
    }
}
