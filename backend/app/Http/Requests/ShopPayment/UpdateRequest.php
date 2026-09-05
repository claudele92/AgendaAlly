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

        return [
            'payment_id' => 'required|integer|exists:payments,id',
            'status'     => 'required|boolean',
            'client_id'  => 'nullable|string',
            'secret_id'  => 'nullable|string',

            'merchant_key'       => [Rule::requiredIf($tag === Payment::TAG_ORANGE), 'nullable', 'string'],
            'subscription_key'   => [Rule::requiredIf($tag === Payment::TAG_MTN), 'nullable', 'string'],
            'api_user'           => [Rule::requiredIf($tag === Payment::TAG_MTN), 'nullable', 'string'],
            'api_key'            => [Rule::requiredIf($tag === Payment::TAG_MTN), 'nullable', 'string'],
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
