<?php

declare(strict_types=1);

namespace App\Http\Requests\ShopPayment;

use App\Http\Requests\BaseRequest;
use App\Models\Payment;
use Illuminate\Validation\Rule;

class UpdateRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $tag = Payment::query()->find($this->input('payment_id'))?->tag;

        /** @var \App\Models\ShopPayment|null $shopPayment */
        $shopPayment = $this->route('shopPayment');

        return [
            'payment_id' => 'required|integer|exists:payments,id',
            'status'     => 'required|boolean',
            'client_id'  => 'nullable|string',
            'secret_id'  => 'nullable|string',

            // Encrypted-at-rest credentials are never returned in
            // plaintext (see ShopPaymentResource) — so once one is
            // already configured, it's only required again if the
            // gateway actually needs it; leaving it blank means keep
            // the existing value (see ShopPaymentService::update()).
            'merchant_key'       => [Rule::requiredIf($tag === Payment::TAG_ORANGE && !$shopPayment?->merchant_key), 'nullable', 'string'],
            'subscription_key'   => [Rule::requiredIf($tag === Payment::TAG_MTN && !$shopPayment?->subscription_key), 'nullable', 'string'],
            'api_user'           => [Rule::requiredIf($tag === Payment::TAG_MTN && !$shopPayment?->api_user), 'nullable', 'string'],
            'api_key'            => [Rule::requiredIf($tag === Payment::TAG_MTN && !$shopPayment?->api_key), 'nullable', 'string'],
            'target_environment' => [
                Rule::requiredIf($tag === Payment::TAG_MTN),
                'nullable',
                'string',
                'regex:/^[a-z]+$/',
            ],
            'currency' => 'nullable|string|size:3',
            'base_url' => 'nullable|url',
        ];
    }
}
