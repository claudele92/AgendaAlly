<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Http\Requests\FilterParamsRequest;
use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\Transaction;
use App\Models\Translation;
use App\Models\Wallet;
use App\Services\PaymentService\StripeService;
use Http;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Redirect;

class StripeController extends PaymentBaseController
{
    // Last-resort fallback if config('app.front_url') itself is broken
    // (e.g. FRONT_URL set to an empty string rather than unset, which
    // env()'s own default can't catch) - never re-derived from the same
    // config that just failed, so this can't also be empty.
    private const FALLBACK_FRONT_URL = 'https://agendaally.com/';

    public function __construct(private StripeService $service)
    {
        parent::__construct($service);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function resultTransaction(Request $request): RedirectResponse
    {

        //[
        //  "num_transaction_from_gu" => "1718355554823"
        //  "num_command" => "1718355553865"
        //  "amount" => "100"
        //  "errorCode" => "500"
        //]
        $status         = $request->input('status');
        $parcelId       = (int)$request->input('parcel_id');
        $adsPackageId   = (int)$request->input('ads_package_id');
        $subscriptionId = (int)$request->input('subscription_id');
        $walletId       = (int)$request->input('wallet_id');
        $bookingId      = (int)$request->input('booking_id');
        $giftCartId     = (int)$request->input('gift_cart_id');
        $memberShipId   = (int)$request->input('member_ship_id');

        csrf_token();

        $to = config('app.front_url') . ($status === 'error' ? 'payment/error' : '');

        if ($parcelId) {
            $to = config('app.front_url') . "parcels/$parcelId";
        } else if ($bookingId) {
            $to = config('app.front_url') . 'appointments';
        } else if ($adsPackageId) {
            $to = config('app.admin_url');
        } else if ($subscriptionId) {
            $to = config('app.admin_url');
        } else if ($giftCartId) {
            $to = config('app.front_url') . 'gift-cards';
        } else if ($memberShipId) {
            $to = config('app.front_url') . 'memberships';
        } else if ($walletId) {

            /** @var Wallet $wallet */
            $wallet = Wallet::with('user.roles')->find($walletId);

            $to = config('app.front_url') . 'wallet';

            if ($wallet?->user?->hasRole(['seller', 'admin', 'moderator', 'deliveryman', 'manager', 'shop_manager'])) {
                $to = config('app.admin_url');
            }

        }

        // A bare relative path here (FRONT_URL/ADMIN_URL unset or blank)
        // would resolve against this API's own host rather than the
        // storefront/admin panel, sending the customer's browser to an
        // unmatched API route that renders as a raw JSON 404 - exactly
        // the failure this guard exists to catch instead of doing that
        // silently.
        if (!filter_var($to, FILTER_VALIDATE_URL)) {
            Log::error('Payment redirect resolved to a non-absolute URL - check FRONT_URL/ADMIN_URL config', [
                'computed_to' => $to,
                'query'       => $request->query(),
            ]);

            $to = self::FALLBACK_FRONT_URL;
        }

        return Redirect::to($to);
    }

    public function mtnProcess(FilterParamsRequest $request): Application|Factory|View
    {
        $buttonText = Translation::where('locale', $request->input('lang'))
            ->where('key', 'mtn')
            ->value('value') ?? 'Continuer';

        return view('mtn', $request->merge(['button_text' => $buttonText])->all());
    }

    /**
     * Async safety net for a charge that succeeded (or failed) after the
     * customer's browser stopped talking to us - the same role PayPal's
     * webhook plays for its own capture. Only `payment_intent.*` events
     * are handled: `data.object.id` on those is the PaymentIntent id
     * (`pi_xxx`), the same value `PaymentProcess.id` was stored under at
     * checkout-session creation time (see StripeService::
     * processTransaction()), so it's kept as the lookup token throughout
     * rather than trusting the webhook body's own status claim - it's
     * used to re-query Stripe's own Checkout Sessions API and only that
     * response's `payment_status` decides paid vs. canceled. (A prior
     * version of this method reassigned the token to the Checkout
     * Session's own id - `cs_xxx` - before the afterHook() lookup, which
     * doesn't match how `PaymentProcess.id` is stored; that meant this
     * webhook could never actually find its row. Fixed by never
     * overwriting the PaymentIntent id.) BaseService::afterHook() is
     * idempotent, so a retried delivery of the same event is safe.
     *
     * @param Request $request
     * @return array
     */
    public function paymentWebHook(Request $request): array
    {
        $eventType = $request->input('type');

        if (!in_array($eventType, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
            return ['status' => false, 'message' => 'unhandled event type'];
        }

        $token = $request->input('data.object.id');

        $payment = Payment::where('tag', Payment::TAG_STRIPE)->first();

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
        $payload        = $paymentPayload?->payload;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . data_get($payload, 'stripe_sk')
        ])
            ->get("https://api.stripe.com/v1/checkout/sessions?limit=1&payment_intent=$token")
            ->json();

        $status = match (data_get($response, 'data.0.payment_status')) {
            'paid'                       => Transaction::STATUS_PAID,
            'unpaid'                     => Transaction::STATUS_CANCELED,
            default                      => 'progress',
        };

        return $this->service->afterHook($token, $status, data_get($response, 'data.0.id'));
    }

}
