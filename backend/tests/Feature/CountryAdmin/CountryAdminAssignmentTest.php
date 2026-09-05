<?php

declare(strict_types=1);

namespace Tests\Feature\CountryAdmin;

use App\Models\CountryAdmin;
use App\Models\CountryRole;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A country admin cannot manage country-admin assignments (a global,
 * superadmin-only action — see CountryAdminController) and cannot see or
 * create roles for a country other than their own, even by naming it
 * explicitly in the request.
 */
class CountryAdminAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('manager', 'web');

        (new \Database\Seeders\CountryPermissionSeeder())->run();

        Cache::put('rjkcvd.ewoidfh', (object) ['local' => true]);
    }

    private function makeCountry(): Country
    {
        $region = Region::query()->create(['active' => true]);

        return Country::query()->create(['region_id' => $region->id, 'active' => true]);
    }

    public function test_country_admin_cannot_manage_country_admin_assignments(): void
    {
        $country = $this->makeCountry();
        $admin   = User::factory()->create();
        $admin->assignRole('manager');
        CountryAdmin::query()->create(['user_id' => $admin->id, 'country_id' => $country->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/admin/country-admins');

        $response->assertStatus(403);
    }

    public function test_superadmin_can_manage_country_admin_assignments(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('manager');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/dashboard/admin/country-admins');

        $response->assertStatus(200);
    }

    public function test_country_admin_cannot_see_another_countrys_roles_even_by_naming_it(): void
    {
        $countryA = $this->makeCountry();
        $countryB = $this->makeCountry();

        $admin = User::factory()->create();
        $admin->assignRole('manager');
        CountryAdmin::query()->create(['user_id' => $admin->id, 'country_id' => $countryA->id]);

        CountryRole::query()->create(['country_id' => $countryB->id, 'name' => "B's Role"]);

        // Naming countryB explicitly must not matter — the acting admin is
        // locked to their own country regardless of any country_id input.
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/admin/country-roles/paginate?country_id=' . $countryB->id);

        $response->assertStatus(200);
        $this->assertSame(0, count($response->json('data')));
    }

    public function test_country_admin_creating_a_role_ignores_a_foreign_country_id_in_the_body(): void
    {
        $countryA = $this->makeCountry();
        $countryB = $this->makeCountry();

        $admin = User::factory()->create();
        $admin->assignRole('manager');
        CountryAdmin::query()->create(['user_id' => $admin->id, 'country_id' => $countryA->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/dashboard/admin/country-roles', [
                'country_id'      => $countryB->id,
                'name'            => 'Sneaky Role',
                'permission_ids'  => [\App\Models\CountryPermission::query()->value('id')],
            ]);

        $response->assertStatus(200);

        $role = CountryRole::query()->where('name', 'Sneaky Role')->first();

        $this->assertNotNull($role);
        $this->assertSame($countryA->id, $role->country_id, 'role must be created under the acting admin\'s own country, not the one named in the request body');
    }
}
