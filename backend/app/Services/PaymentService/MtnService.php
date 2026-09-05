<?php
declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Models\Payment;
use App\Models\PaymentProcess;
use App\Models\Payout;
use App\Models\ShopPayment;
use Exception;
use Http;
use Illuminate\Database\Eloquent\Model;
use Str;
use Throwable;

/**
 * MTN Mobile Money via MTN's own Collections API (momodeveloper.mtn.com)
 * — not a third-party aggregator. Credentials/target_environment/currency
 * are all per-shop config (see ShopPayment); nothing here branches on
 * country — target_environment and currency are just parameters the
 * shop's config resolves.
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
        $shopId  = $this->resolveGatewayShopId(data_get($before, 'model_type'), $modelId);

        /** @var ShopPayment|null $shopPayment */
        $shopPayment = ShopPayment::forShopAndPayment($shopId, $payment->id);

        if (!$shopPayment?->subscription_key || !$shopPayment->api_user || !$shopPayment->api_key || !$shopPayment->target_environment) {
            throw new Exception('This shop has not configured MTN Mobile Money yet');
        }

        $token = $this->getToken($shopPayment);

        $referenceId = Str::uuid()->toString();
        $host        = request()->getSchemeAndHttpHost();
        $amount      = number_format((float) data_get($before, 'total_price') / 100, 2, '.', '');

        $response = Http::withHeaders($this->requestHeaders($shopPayment, $token, $referenceId))
            ->post($this->baseUrl($shopPayment) . '/collection/v1_0/requesttopay', [
                'amount'     => $amount,
                'currency'   => $shopPayment->currency,
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
    public function checkStatus(ShopPayment $shopPayment, string $referenceId): array
    {
        $token = $this->getToken($shopPayment);

        $response = Http::withHeaders($this->requestHeaders($shopPayment, $token, $referenceId))
            ->get("{$this->baseUrl($shopPayment)}/collection/v1_0/requesttopay/$referenceId");

        if (!$response->successful()) {
            throw new Exception('Unable to reach MTN status endpoint', $response->status());
        }

        return $response->json();
    }

    /**
     * @throws Exception
     */
    private function getToken(ShopPayment $shopPayment): string
    {
        $credentials = base64_encode("{$shopPayment->api_user}:{$shopPayment->api_key}");

        $response = Http::withHeaders([
            'Authorization'             => "Basic $credentials",
            'Ocp-Apim-Subscription-Key' => $shopPayment->subscription_key,
        ])->post($this->baseUrl($shopPayment) . '/collection/token/');

        $json = $response->json();

        if (!$response->successful() || !isset($json['access_token'])) {
            throw new Exception('Unable to obtain an MTN access token');
        }

        return $json['access_token'];
    }

    private function requestHeaders(ShopPayment $shopPayment, string $token, string $referenceId): array
    {
        return [
            'Content-Type'              => 'application/json',
            'Authorization'             => "Bearer $token",
            'X-Reference-Id'            => $referenceId,
            'X-Target-Environment'      => $shopPayment->target_environment,
            'Ocp-Apim-Subscription-Key' => $shopPayment->subscription_key,
        ];
    }

    private function baseUrl(ShopPayment $shopPayment): string
    {
        return rtrim($shopPayment->base_url ?: 'https://sandbox.momodeveloper.mtn.com', '/');
    }
}
