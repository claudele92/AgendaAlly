<?php
declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class InviteUpdateRequest extends BaseRequest
{
    /**
     * Editing an existing invitation only ever changes which shop_role or
     * branch (shop_location) it's tied to - user_id and the platform role
     * are not editable here. See Seller\InviteController::update().
     *
     * @return array
     */
    public function rules(): array
    {
        $user   = auth('sanctum')->user();
        $shopId = $this->input('shop_id') ?? $user?->shop?->id ?? $user?->moderatorShop?->id;

        return [
            'shop_role_id' => [
                'nullable',
                'integer',
                Rule::exists('shop_roles', 'id')->where('shop_id', $shopId),
            ],
            'shop_location_id' => [
                'nullable',
                'integer',
                Rule::exists('shop_locations', 'id')->where('shop_id', $shopId),
            ],
        ];
    }
}
