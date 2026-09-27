<?php
declare(strict_types=1);

namespace App\Services\PaymentService;

use Exception;
use Str;
use Throwable;
use Stripe\Stripe;
use App\Models\Payout;
use App\Models\Payment;
use App\Models\ShopAdsPackage;
use App\Models\Subscription;
use Stripe\Checkout\Session;
use App\Models\PaymentProcess;
use Illuminate\Database\Eloquent\Model;
use Stripe\Exception\ApiErrorException;

class StripeService extends BaseService
{
    /**
     * Platform-fee purchases only — Apple Pay/Google Pay ride the same
     * hosted Checkout Session used everywhere else (they're wallet UI
     * variants of 'card', not a separate integration), scoped to just
     * these two transaction types via a dedicated Stripe "Payment Method
     * Configuration" (see paymentMethodParams()) rather than the
     * account-wide default, which stays card-only for regular checkout.
     */
    private const WALLET_MODEL_TYPES = [Subscription::class, ShopAdsPackage::class];

    /**
     * Stripe's documented zero-decimal currencies — unit_amount must be
     * passed as the amount in the currency's OWN unit, never multiplied
     * by 100. Every before*() helper in BaseService (beforeCart,
     * beforeBooking, beforeMemberShip, beforeParcel, beforeSubscription,
     * beforePackage, beforeWallet, beforeGiftCart, beforeAuction)
     * unconditionally does `round($totalPrice * 100, 2)`, assuming a
     * 2-decimal currency (correct for USD/EUR-style checkouts, the only
     * ones this integration has ever actually been used with) - none of
     * them special-case a zero-decimal currency, and neither does this
     * service. Rather than teach every call site (and Checkout Session
     * line item) the correct scaling for each one - real work, not yet
     * done - this list exists to block the currencies that *100 would
     * silently overcharge by 100x, until that support is actually built.
     * Verify against https://docs.stripe.com/currencies before adding or
     * removing entries; this is not fetched live.
     */
    public const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF',
    ];

    /**
     * Whether Stripe can safely be offered/charged for this currency
     * today. False for any currency on ZERO_DECIMAL_CURRENCIES (see
     * above) — used both to hide Stripe from the customer-facing payment
     * method list (PaymentController::index()) and, as a hard backstop,
     * to reject processTransaction() below if reached anyway.
     */
    public static function supportsCurrency(?string $currency): bool
    {
        if (empty($currency)) {
            return true;
        }

        return !in_array(Str::upper($currency), self::ZERO_DECIMAL_CURRENCIES, true);
    }

    protected function getModelClass(): string
    {
        return Payout::class;
    }

    /**
     * @param array $data
     * @return PaymentProcess|Model
     * @throws ApiErrorException|Throwable
     */
    public function processTransaction(array $data): Model|PaymentProcess
    {
        /** @var Payment $payment */
        $payment = Payment::with(['paymentPayload'])
            ->where('tag', Payment::TAG_STRIPE)
            ->first();

        $payload = $this->requireConfiguredPayload($payment?->paymentPayload?->payload, 'Stripe');

        Stripe::setApiKey(data_get($payload, 'stripe_sk'));

        [$key, $before] = $this->getPayload($data, $payload);

        $currency = data_get($before, 'currency');

        // Hard backstop behind the payment-method-list filter
        // (PaymentController::index()) — if this is ever reached anyway
        // (a stale client, a direct API call), fail loudly rather than
        // silently overcharge 100x on a zero-decimal currency.
        if (!self::supportsCurrency($currency)) {
            throw new Exception(
                "Stripe payment is not supported for $currency currency. Please select a supported local payment method.",
                400
            );
        }

        $host = request()->getSchemeAndHttpHost();

        $modelId = data_get($before, 'model_id');

        $session = Session::create([
            ...$this->paymentMethodParams(data_get($before, 'model_type'), $payload),
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => Str::lower(data_get($before, 'currency')),
                        'product_data' => [
                            'name' => 'Payment'
                        ],
                        'unit_amount' => data_get($before, 'total_price'),
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'success_url' => "$host/payment-success?token={CHECKOUT_SESSION_ID}&$key=$modelId&lang=$this->language",
            'cancel_url'  => "$host/payment-success?token={CHECKOUT_SESSION_ID}&$key=$modelId&lang=$this->language&status=error",
        ]);

        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_type' => data_get($before, 'model_type'),
            'model_id'   => $modelId,
        ], [
            'id' => $session->payment_intent ?? $session->id,
            'data' => array_merge([
                'url'        => $session->url,
                'payment_id' => $payment->id,
            ], $before)
        ]);
    }

    /**
     * A Checkout Session takes either `payment_method_types` or
     * `payment_method_configuration`, never both. Everywhere except
     * Subscription/ShopAdsPackage purchases keeps the existing
     * card-only default unchanged. Platform-fee purchases use the
     * dedicated wallet configuration only if the superadmin has
     * actually set one on the Stripe payload (Payment Payloads screen)
     * — if they haven't, this falls back to plain card, same as every
     * other transaction type, rather than silently failing the session.
     */
    private function paymentMethodParams(?string $modelType, ?array $payload): array
    {
        $walletConfigurationId = data_get($payload, 'wallet_payment_method_configuration');

        if (in_array($modelType, self::WALLET_MODEL_TYPES, true) && $walletConfigurationId) {
            return ['payment_method_configuration' => $walletConfigurationId];
        }

        return ['payment_method_types' => ['card']];
    }

}
