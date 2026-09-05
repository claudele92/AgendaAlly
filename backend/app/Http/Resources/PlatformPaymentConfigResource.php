<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformPaymentConfigResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\PlatformPaymentConfig|JsonResource $this */
        return [
            'id'         => $this->id,
            'country_id' => $this->country_id,
            'payment_id' => $this->payment_id,
            'status'     => $this->status,
            'client_id'  => $this->client_id,

            // Credentials are encrypted at rest and never round-tripped
            // back out in plaintext — only whether each has been set.
            'merchant_key_configured'     => (bool) $this->merchant_key,
            'subscription_key_configured' => (bool) $this->subscription_key,
            'api_user_configured'         => (bool) $this->api_user,
            'api_key_configured'          => (bool) $this->api_key,
            'target_environment'          => $this->target_environment,
            'currency'                    => $this->currency,
            'base_url'                    => $this->base_url,

            'created_by' => $this->created_by,
            'country'    => $this->whenLoaded('country', fn () => [
                'id'   => $this->country->id,
                'name' => $this->country->translation?->title,
            ]),
            'payment'    => PaymentResource::make($this->whenLoaded('payment')),
            'created_at' => $this->when($this->created_at, $this->created_at?->format('Y-m-d H:i:s')),
        ];
    }
}
