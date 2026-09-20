<?php
declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;
use App\Models\Payout;
use App\Models\Settings;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Str;

class PayPalService extends BaseService
{
    protected function getModelClass(): string
    {
        return Payout::class;
    }

    /**
     * @param array $data
     * @return PaymentProcess
     * @throws GuzzleException
     * @throws Exception
     */
    public function processTransaction(array $data): PaymentProcess
    {
        $payment        = Payment::where('tag', Payment::TAG_PAY_PAL)->first();
        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();

        $payload        = $paymentPayload?->payload;

        $url            = 'https://api-m.sandbox.paypal.com';
        $clientId       = data_get($payload, 'paypal_sandbox_client_id');
        $clientSecret   = data_get($payload, 'paypal_sandbox_client_secret');

        if (data_get($payload, 'paypal_mode', 'sandbox') === 'live') {
            $url            = 'https://api-m.paypal.com';
            $clientId       = data_get($payload, 'paypal_live_client_id');
            $clientSecret   = data_get($payload, 'paypal_live_client_secret');
        }

        $provider = new Client();
        $responseAuth = $provider->post("$url/v1/oauth2/token", [
            'auth' => [
                $clientId,
                $clientSecret,
            ],
            'form_params' => [
                'grant_type' => 'client_credentials',
            ]
        ]);

        $responseAuth = json_decode($responseAuth->getBody()->getContents(), true);

        [$key, $before] = $this->getPayload($data, $payload);

        $modelId     = data_get($before, 'model_id');
        $tokenType   = data_get($responseAuth, 'token_type', 'Bearer');
        $accessToken = data_get($responseAuth, 'access_token');
        $host        = request()->getSchemeAndHttpHost();
        $title       = Settings::where('key', 'title')->first()?->title ?? env('APP_NAME');

        [$settlementCurrency, $settlementAmount] = $this->resolveSettlementAmount($before, $payment->id);

        $response = $provider->post("$url/v2/checkout/orders", [
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $settlementCurrency,
                            'value' => ceil($settlementAmount / 100)
                        ]
                    ]
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'brand_name'                => $title,
                            'locale'                    => 'en-US',
                            'landing_page'              => 'LOGIN',
                            'shipping_preference'       => 'NO_SHIPPING',
                            'user_action'               => 'PAY_NOW',
                            'return_url'                => "$host/payment-success?$key=$modelId&lang=$this->language",
                            'cancel_url'                => "$host/payment-success?$key=$modelId&lang=$this->language&status=error"
                        ]
                    ]
                ]
            ],
            'headers' => [
                'Accept-Language' => 'en_US',
                'Content-Type'    => 'application/json',
                'Authorization'   => "$tokenType $accessToken",
            ],
        ]);

        $response = json_decode($response->getBody()->getContents(), true);

        if (data_get($response, 'error')) {

            $message = data_get($response, 'message', 'Something went wrong');

            $message = implode(',', is_array($message) ? $message : [$message]);

            throw new Exception($message, 400);
        }

        $links = collect(data_get($response, 'links'));

        $checkoutNowUrl = $links->where('rel', 'approve')->first()['href'] ?? null;
        $checkoutNowUrl = $checkoutNowUrl ?? $links->where('rel', 'payer-action')->first()['href'] ?? null;
        $checkoutNowUrl = $checkoutNowUrl ?? $links->first()['href'] ?? null;

        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_type' => data_get($before, 'model_type'),
            'model_id'   => data_get($before, 'model_id'),
        ], [
            'id' => data_get($response, 'id'),
            'data' => array_merge([
                'url'        => $checkoutNowUrl,
                'payment_id' => $payment->id,
            ], $before)
        ]);

    }

    /**
     * PayPal only accepts a fixed, short list of transaction currencies -
     * XAF/XOF among others are not on it - and knows nothing about this
     * platform's base-currency setting or a booking/cart's own
     * country-derived currency, neither of which it can be relied on to
     * accept. Resolves a settlement currency via the same per-country
     * PlatformPaymentConfig override MTN/Orange already use (see
     * BaseService::resolveGatewayConfig()), falling back to USD when
     * nothing has been configured for this country yet, and converts
     * total_price - stored in the booking/cart's own currency - into
     * that currency so the amount actually charged stays correct.
     *
     * @return array{0: string, 1: float} [currency_code, amount_in_that_currency]
     */
    private function resolveSettlementAmount(array $before, int $paymentId): array
    {
        $totalPrice = (float) data_get($before, 'total_price');
        $fromTitle  = Str::upper(data_get($before, 'currency'));

        $config = null;

        try {
            $config = $this->resolveGatewayConfig($before, $paymentId);
        } catch (Exception) {
            // Not a booking/cart/subscription/ads-package payment (e.g. a
            // gift card or membership purchase) - no country to resolve a
            // currency override from, fall back to the native currency.
        }

        $toTitle = $config?->getCurrency() ? Str::upper($config->getCurrency()) : 'USD';

        if ($toTitle === $fromTitle) {
            return [$fromTitle, $totalPrice];
        }

        $currencies = Currency::currenciesList();
        $from       = $currencies->firstWhere('title', $fromTitle);
        $to         = $currencies->firstWhere('title', $toTitle);

        if (!$from || !$to) {
            return [$fromTitle, $totalPrice];
        }

        return [$toTitle, Currency::convert($totalPrice, $to->id, $from->id)];
    }

}
