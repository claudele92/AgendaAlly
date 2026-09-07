<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Invitation;
use App\Models\Shop;
use App\Models\ShopLocation;
use App\Models\ShopRole;
use App\Models\User;
use App\Traits\Loggable;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * A real demo staff member for shop 501 (Cameroon) — a Branch Manager
 * (see DemoStaffRolesSeeder) explicitly assigned to the Douala branch
 * (see DemoAfricaSeeder), so the Staff screen and branch-assignment
 * dropdown have real data beyond just the role/location option lists.
 *
 * Runs after DemoStaffRolesSeeder (needs the shop_role) and DemoAfricaSeeder
 * (needs the shop_location and the user 114 from UserSeeder). Keyed by
 * updateOrCreate(user_id, shop_id), so safe to run more than once.
 */
class DemoStaffInvitationSeeder extends Seeder
{
    use Loggable;

    // See UserSeeder — the Cameroon demo seller (shop owner) and the demo
    // staff member being invited.
    private const CAMEROON_SELLER_USER_ID = 107;
    private const BRANCH_MANAGER_USER_ID = 114;

    public function run(): void
    {
        try {
            $shop = Shop::where('user_id', self::CAMEROON_SELLER_USER_ID)->first();
            $user = User::find(self::BRANCH_MANAGER_USER_ID);

            if (!$shop || !$user) {
                return;
            }

            $shopRole = ShopRole::where('shop_id', $shop->id)->where('name', 'Branch Manager')->first();

            $douala = ShopLocation::where('shop_id', $shop->id)
                ->whereHas('city.translation', fn($q) => $q->where('title', 'Douala'))
                ->first();

            if (!$shopRole || !$douala) {
                return;
            }

            $invitation = Invitation::updateOrCreate([
                'user_id' => $user->id,
                'shop_id' => $shop->id,
            ], [
                'created_by'        => $shop->user_id,
                'role'              => 'shop_manager',
                'shop_role_id'      => $shopRole->id,
                'shop_location_id'  => $douala->id,
                'status'            => Invitation::ACCEPTED,
            ]);

            // Mirrors what InviteService::changeStatus() does on a real
            // acceptance — the invite's role becomes an actual platform
            // role on the user, which is what gates seller-dashboard entry.
            $roles   = $user->roles?->pluck('name')?->toArray() ?? [];
            $roles[] = $invitation->role;
            $user->syncRoles($roles);
        } catch (Throwable $e) {
            $this->error($e);
        }
    }
}
