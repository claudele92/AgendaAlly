<?php

declare(strict_types=1);

namespace App\Services\CountryAdminService;

use App\Helpers\ResponseError;
use App\Models\CountryAdmin;
use App\Services\CoreService;
use Throwable;

/**
 * Assigns/reassigns a user's single restricted country — purely additive
 * against Spatie's roles. This table only restricts an existing
 * admin/manager to one country; it never grants dashboard access on its
 * own. Use the existing users/{uuid}/role/update endpoint to give the
 * user the 'manager' role first if they don't already have it.
 */
final class CountryAdminService extends CoreService
{
    protected function getModelClass(): string
    {
        return CountryAdmin::class;
    }

    public function create(array $data): array
    {
        try {
            $admin = CountryAdmin::query()->create([
                'user_id'    => $data['user_id'],
                'country_id' => $data['country_id'],
                'created_by' => auth('sanctum')->id(),
            ]);

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $admin->load(['user', 'country.translation']),
            ];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_501, 'message' => $e->getMessage()];
        }
    }

    public function update(CountryAdmin $admin, array $data): array
    {
        try {
            $admin->update(['country_id' => $data['country_id']]);

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $admin->load(['user', 'country.translation']),
            ];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_502, 'message' => $e->getMessage()];
        }
    }

    public function delete(CountryAdmin $admin): array
    {
        try {
            $admin->delete();

            return ['status' => true, 'code' => ResponseError::NO_ERROR];
        } catch (Throwable $e) {
            $this->error($e);
            return ['status' => false, 'code' => ResponseError::ERROR_502, 'message' => $e->getMessage()];
        }
    }
}
