<?php
declare(strict_types=1);

namespace App\Services\ShopServices;

use App\Helpers\ResponseError;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopLocation;
use App\Models\ShopPayment;
use App\Services\CoreService;
use Throwable;

class ShopPaymentService extends CoreService
{
    protected function getModelClass(): string
    {
        return ShopPayment::class;
    }

    /**
     * Gateways whose merchant registration is done by the receiving shop
     * itself — there's no platform-level alternative, so a shop's config
     * for these is mandatory, gated on their country actually offering
     * the gateway, and defaults its currency from that country.
     */
    private const SHOP_CREDENTIAL_TAGS = [Payment::TAG_ORANGE, Payment::TAG_MTN];

    public function create(array $data): array
    {
        try {
            $prepared = $this->prepareGatewayConfig($data);

            if (!data_get($prepared, 'status')) {
                return $prepared;
            }

            $this->model()->create(data_get($prepared, 'data'));

            return ['status' => true, 'code' => ResponseError::NO_ERROR];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_501];
        }
    }

    public function update(array $data, ShopPayment $shopPayment): array
    {
        try {
            $data['shop_id'] ??= $shopPayment->shop_id;

            $prepared = $this->prepareGatewayConfig($data);

            if (!data_get($prepared, 'status')) {
                return $prepared;
            }

            $shopPayment->update(collect(data_get($prepared, 'data'))->except('shop_id')->all());

            return ['status' => true, 'code' => ResponseError::NO_ERROR];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_502];
        }
    }

    /**
     * For Orange/MTN specifically: rejects a shop whose country doesn't
     * offer the gateway (per country_payments), and fills in a default
     * currency from that country when the shop didn't explicitly
     * override it. A no-op for every other gateway.
     */
    private function prepareGatewayConfig(array $data): array
    {
        $payment = Payment::query()->find(data_get($data, 'payment_id'));

        if (!$payment || !in_array($payment->tag, self::SHOP_CREDENTIAL_TAGS, true)) {
            return ['status' => true, 'data' => $data];
        }

        /** @var Shop|null $shop */
        $shop = Shop::find(data_get($data, 'shop_id'));

        $country = $shop?->checkoutCountry(ShopLocation::PRODUCT) ?? $shop?->checkoutCountry(ShopLocation::SERVICE);

        if (!$country || !$country->activePaymentIds()->contains($payment->id)) {
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_400,
                'message' => __('errors.' . ResponseError::ERROR_400, locale: $this->language),
            ];
        }

        if (empty($data['currency'])) {
            $data['currency'] = $country->currency?->title;
        }

        if (empty($data['currency'])) {
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_400,
                'message' => __('errors.' . ResponseError::ERROR_400, locale: $this->language),
            ];
        }

        return ['status' => true, 'data' => $data];
    }

    public function delete(?array $ids = [], ?int $shopId = null): array
    {
        foreach ($this->model()->find(is_array($ids) ? $ids : []) as $value) {
            /** @var ShopPayment $value */
            if ($value->shop_id === $shopId) {
                $value->delete();
            }
        }

        return ['status' => true, 'code' => ResponseError::NO_ERROR];
    }

    public function setActive(int $id, int $shopId): array
    {
        try {
            $shopPayment = ShopPayment::find($id);

            if ($shopPayment->shop_id !== $shopId) {
                return ['status' => false, 'code' => ResponseError::ERROR_204];
            }

            $shopPayment->update([
                'active' => !$shopPayment->status
            ]);

            return ['status' => true, 'code' => ResponseError::NO_ERROR];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_502];
        }
    }
}
