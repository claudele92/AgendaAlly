<?php

declare(strict_types=1);

namespace App\Services\CountryRoleService;

use App\Helpers\ResponseError;
use App\Models\CountryRole;
use App\Services\CoreService;
use Throwable;

final class CountryRoleService extends CoreService
{
    protected function getModelClass(): string
    {
        return CountryRole::class;
    }

    public function create(int $countryId, array $data): array
    {
        try {
            /** @var CountryRole $role */
            $role = CountryRole::query()->create([
                'country_id' => $countryId,
                'name'       => $data['name'],
            ]);

            $role->permissions()->sync($data['permission_ids'] ?? []);

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $role->load('permissions'),
            ];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_501, 'message' => $e->getMessage()];
        }
    }

    public function update(CountryRole $role, array $data): array
    {
        try {
            $role->update(['name' => $data['name']]);
            $role->permissions()->sync($data['permission_ids'] ?? []);

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $role->load('permissions'),
            ];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_502, 'message' => $e->getMessage()];
        }
    }

    public function delete(CountryRole $role): array
    {
        // Blocked, not reassigned/nulled, if any invitation still references
        // this role — matches the country_invitations.country_role_id
        // restrictOnDelete FK exactly, mirrors ShopRoleService::delete().
        if ($role->invitations()->exists()) {
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => __('errors.' . ResponseError::ERROR_504, locale: $this->language),
            ];
        }

        try {
            $role->permissions()->detach();
            $role->delete();

            return ['status' => true, 'code' => ResponseError::NO_ERROR];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_502, 'message' => $e->getMessage()];
        }
    }
}
