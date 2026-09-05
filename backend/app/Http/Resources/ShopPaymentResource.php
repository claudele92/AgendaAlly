<?php
declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShopPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array
     */
    public function toArray($request): array
    {
        /** @var ShopPayment|JsonResource $this */
        return [
            'id'            => $this->id,
            'shop_id'       => $this->shop_id,
            'status'        => $this->status,
            'client_id'     => $this->client_id,
            'secret_id'     => $this->secret_id,

            // Orange Money / MTN Mobile Money config. Credentials are
            // encrypted at rest and never round-tripped back out in
            // plaintext — only whether each has been set.
            'merchant_key_configured'     => (bool) $this->merchant_key,
            'subscription_key_configured' => (bool) $this->subscription_key,
            'api_user_configured'         => (bool) $this->api_user,
            'api_key_configured'          => (bool) $this->api_key,
            'target_environment'         => $this->target_environment,
            'currency'                   => $this->currency,
            'base_url'                   => $this->base_url,

            'payment'       => PaymentResource::make($this->whenLoaded('payment'))
        ];
    }
}
