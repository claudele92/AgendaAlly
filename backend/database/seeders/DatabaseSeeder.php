<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use Database\Seeders\Support\CountryDefaultsBackfiller;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call(LanguageSeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(NotificationSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(ShopPermissionSeeder::class);
        $this->call(CountryPermissionSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(ShopTagSeeder::class);
        $this->call(PaymentSeeder::class);
        $this->call(SubscriptionSeeder::class);
        $this->call(TranslationSeeder::class);
        $this->call(EmailSettingSeeder::class);
        $this->call(SmsGatewaySeeder::class);
        $this->call(UnitSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(OrderSeeder::class);
//        $this->call(RegionSeeder::class);

        // Cameroon/Burkina Faso demo geography + the two demo sellers'
        // ShopLocation rows — needs UserSeeder's shops to already exist,
        // and must run before CountryDefaultsBackfiller below so its two
        // new countries get backfilled country_payments too.
        $this->call(DemoAfricaSeeder::class);

        // Re-runs the country currency/payment-gateway backfill from the
        // 2026_09_05_030000 migration now that seed data exists. On
        // `migrate:fresh --seed`, that migration ran before any of the
        // seeders above, so currencies/countries/payments were all still
        // empty at the time and it backfilled nothing — this call is what
        // actually populates country defaults for a fresh install. It's a
        // no-op against anything already backfilled (e.g. a real deploy's
        // `php artisan migrate`, where the migration already found data).
        CountryDefaultsBackfiller::run();

//        if (app()->environment() == 'local') {
//            Category::factory()->hasTranslations(1)->count(10)->create();
//            Brand::factory()->count(10)->create();
//            ExtraGroup::factory()->hasTranslation(1)->hasExtraValues(3)->count(5)->create();
//            User::factory()->has(
//                Shop::factory()->hasTranslation(1)->has(
//                    Product::factory()->hasTranslation(1)->hasExtras(2)->hasProperties(10)->count(rand(10,30))
//                )->count(1)
//            )->count(100)->create();
//            Order::factory()->has(OrderDetail::factory()->hasProducts(2)->count(3))->count(10)->create();
//        }
    }
}
