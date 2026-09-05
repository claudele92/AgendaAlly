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
use Stripe\Exception\ApiErrorException;
use Throwable;

class OrangeService extends BaseService
{
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
        $payment = Payment::query()->firstOrCreate(
            ['tag' => Payment::TAG_ORANGE],
            ['active' => true, 'input' => 15]
        );

        [$key, $before] = $this->getPayload($data, []);

        $modelId = data_get($before, 'model_id');
        $shopId  = $this->resolveGatewayShopId(data_get($before, 'model_type'), $modelId);

        /** @var ShopPayment|null $shopPayment */
        $shopPayment = ShopPayment::forShopAndPayment($shopId, $payment->id);

        if (!$shopPayment?->merchant_key) {
            throw new Exception('This shop has not configured Orange Money yet');
        }

        // Orange's OAuth client credentials: client_id is the existing
        // shared shop_payments column, merchant_key is the client_secret
        // (encrypted at rest — see ShopPayment).
        $baseUrl = $shopPayment->base_url ?: 'https://api.sandbox.orange-sonatel.com';

        $tokenPayload = $this->getToken($baseUrl, [
            'client_id'     => $shopPayment->client_id,
            'client_secret' => $shopPayment->merchant_key,
        ]);

        $token = $tokenPayload['token']['access_token'];

        $host = request()->getSchemeAndHttpHost();

        $amount = (int) round((float) data_get($before, 'total_price') / 100);

        $request = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => "Bearer $token",
        ])
            ->withoutVerifying()
            ->post("$baseUrl/api/eWallet/v4/qrcode", [
                'amount' => ['unit' => $shopPayment->currency, 'value' => $amount],
                'callbackCancelUrl'  => "$host/api/v1/webhook/orange/payment",
                'callbackSuccessUrl' => "$host/api/v1/webhook/orange/payment",
                'code' => 123456,
                'metadata' => (object) [$key => $modelId],
                'name' => auth('sanctum')->user()->full_name,
                'validity' => 15,
            ])
            ->json();

        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_type' => data_get($before, 'model_type'),
            'model_id'   => $modelId,
        ], [
            'id' => Str::uuid()->toString(),
            'data' => array_merge([
                'url'        => $request['deepLink'],
                'payment_id' => $payment->id,
                'body'       => $request,
            ], $before)
        ]);
    }

    /**
     * @param string $baseUrl
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function getToken(string $baseUrl, array $payload): array
    {
        try {

            $getToken = Http::asForm()
                ->withoutVerifying()
                ->post("$baseUrl/oauth/token", [
                    'client_id'     => $payload['client_id'] ?? null,
                    'client_secret' => $payload['client_secret'] ?? null,
                    'grant_type'    => 'client_credentials',
                ])->json();

            if (!isset($getToken['access_token'])) {
                throw new Exception();
            }

            $payload['token'] = $getToken;

        } catch (Throwable $e) {
            throw new Exception('403 error');
        }

        return $payload;
    }
}
