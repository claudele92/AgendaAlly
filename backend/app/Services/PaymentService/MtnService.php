<?php
declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Models\Payment;
use App\Models\PaymentProcess;
use App\Models\Payout;
use App\Services\PaymentService\Contracts\GatewayConfig;
use Exception;
use Http;
use Illuminate\Database\Eloquent\Model;
use Str;
use Throwable;

/**
 * MTN Mobile Money via MTN's own Collections API (momodeveloper.mtn.com)
 * — not a third-party aggregator. Credentials/target_environment/currency
 * all come from a GatewayConfig (either a shop's own ShopPayment for
 * customer-facing checkout, or the platform's PlatformPaymentConfig for
 * a platform-fee purchase — see BaseService::resolveGatewayConfig());
 * nothing here branches on country — target_environment and currency
 * are just parameters the resolved config supplies.
 */
class MtnService extends BaseService
{
    protected function getModelClass(): string
    {
        return Payout::class;
    }

    /**
     * @param array $data
     * @return PaymentProcess|Model
     * @throws Throwable
     */
    public function processTransaction(array $data): Model|PaymentProcess
    {
        /** @var Payment $payment */
        $payment = Payment::query()->firstOrCreate(
            ['tag' => Payment::TAG_MTN],
            ['active' => true, 'input' => 15]
        );

        [, $before] = $this->getPayload($data, []);

        $modelId = data_get($before, 'model_id');
        $config  = $this->resolveGatewayConfig($before, $payment->id);

        if (!$config?->hasMtnCredentials()) {
            throw new Exception('MTN Mobile Money has not been configured for this transaction yet');
        }

        $token = $this->getToken($config);

        $referenceId = Str::uuid()->toString();
        $host        = request()->getSchemeAndHttpHost();
        $amount      = number_format((float) data_get($before, 'total_price') / 100, 2, '.', '');

        $response = Http::withHeaders($this->requestHeaders($config, $token, $referenceId))
            ->post($this->baseUrl($config) . '/collection/v1_0/requesttopay', [
                'amount'     => $amount,
                'currency'   => $config->getCurrency(),
                'externalId' => $referenceId,
                'payer'      => [
                    'partyIdType' => 'MSISDN',
                    'partyId'     => data_get($data, 'phone'),
                ],
                'payerMessage' => "Payment #$referenceId",
                'payeeNote'    => "Payment #$referenceId",
                'callbackUrl'  => "$host/api/v1/webhook/mtn/payment",
            ]);

        if ($response->status() !== 202) {
            $json = $response->json();
            throw new Exception(data_get($json, 'message', 'MTN request failed'), $response->status());
        }

        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_type' => data_get($before, 'model_type'),
            'model_id'   => $modelId,
        ], [
            'id'   => $referenceId,
            'data' => array_merge([
                'payment_id'        => $payment->id,
                'mtn_reference_id'  => $referenceId,
                'mtn_resolved'      => false,
                'requested_at'      => now()->toIso8601String(),
            ], $before)
        ]);
    }

    /**
     * Polls MTN's own status endpoint for a request-to-pay reference —
     * used both by an explicit status-check call and by the scheduled
     * reconciliation job, since MTN's webhook delivery isn't guaranteed.
     *
     * @throws Exception
     */
    public function checkStatus(GatewayConfig $config, string $referenceId): array
    {
        $token = $this->getToken($config);

        $response = Http::withHeaders($this->requestHeaders($config, $token, $referenceId))
            ->get("{$this->baseUrl($config)}/collection/v1_0/requesttopay/$referenceId");

        if (!$response->successful()) {
            throw new Exception('Unable to reach MTN status endpoint', $response->status());
        }

        return $response->json();
    }

    /**
     * @throws Exception
     */
    private function getToken(GatewayConfig $config): string
    {
        $credentials = base64_encode("{$config->getApiUser()}:{$config->getApiKey()}");

        $response = Http::withHeaders([
            'Authorization'             => "Basic $credentials",
            'Ocp-Apim-Subscription-Key' => $config->getSubscriptionKey(),
        ])->post($this->baseUrl($config) . '/collection/token/');

        $json = $response->json();

        if (!$response->successful() || !isset($json['access_token'])) {
            throw new Exception('Unable to obtain an MTN access token');
        }

        return $json['access_token'];
    }

    private function requestHeaders(GatewayConfig $config, string $token, string $referenceId): array
    {
        return [
            'Content-Type'              => 'application/json',
            'Authorization'             => "Bearer $token",
            'X-Reference-Id'            => $referenceId,
            'X-Target-Environment'      => $config->getTargetEnvironment(),
            'Ocp-Apim-Subscription-Key' => $config->getSubscriptionKey(),
        ];
    }

    private function baseUrl(GatewayConfig $config): string
    {
        return rtrim($config->getBaseUrl() ?: 'https://sandbox.momodeveloper.mtn.com', '/');
    }
}
