<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Region;
use App\Models\Shop;
use App\Models\ShopLocation;
use App\Traits\Loggable;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Demo geography for Cameroon (XAF) and Burkina Faso (XOF), both under
 * Africa, plus the ShopLocation rows that put the two demo sellers' shops
 * (see UserSeeder, users 107 and 113) into those countries — a shop has no
 * country_id column of its own; Shop::checkoutCountry() resolves it
 * entirely from productLocation()/serviceLocation(), both HasOne
 * ShopLocation scoped by type. Both of user 107's branches share the same
 * type and country (Cameroon), so they can't trip
 * ShopLocationService::conflictingCountryLocation()'s same-country-per-type
 * rule.
 *
 * Deliberately NOT part of RegionSeeder. RegionSeeder is disabled (see
 * DatabaseSeeder) and populates the entire world from countries.json — a
 * separate, larger, previously-flagged-but-not-yet-decided change. This is
 * a small, self-contained addition of just the demo data this task needs,
 * safe to run whether or not RegionSeeder ever gets enabled: it looks up
 * every row by translation title before creating (same idempotency
 * pattern RegionSeeder itself uses), so it no-ops cleanly against a
 * RegionSeeder-populated database that already has these countries, and
 * never creates a duplicate Africa region on a second run.
 */
class DemoAfricaSeeder extends Seeder
{
    use Loggable;

    // See UserSeeder — the two demo sellers' user ids.
    private const CAMEROON_SELLER_USER_ID = 107;
    private const BURKINA_FASO_SELLER_USER_ID = 113;

    public function run(): void
    {
        try {
            $locale = data_get(Language::where('default', 1)->first(), 'locale', 'en');

            $africa = $this->region('Africa', $locale);

            $cameroon = $this->country($africa, 'Cameroon', 'cm', 'XAF', $locale);
            $douala   = $this->city($africa, $cameroon, 'Douala', $locale);
            $yaounde  = $this->city($africa, $cameroon, 'Yaoundé', $locale);

            $burkinaFaso   = $this->country($africa, 'Burkina Faso', 'bf', 'XOF', $locale);
            $ouagadougou   = $this->city($africa, $burkinaFaso, 'Ouagadougou', $locale);
            $boboDioulasso = $this->city($africa, $burkinaFaso, 'Bobo-Dioulasso', $locale);

            $cameroonShop = Shop::where('user_id', self::CAMEROON_SELLER_USER_ID)->first();

            if ($cameroonShop) {
                $this->shopLocation($cameroonShop, $africa, $cameroon, $yaounde);
                $this->shopLocation($cameroonShop, $africa, $cameroon, $douala);
            }

            $burkinaShop = Shop::where('user_id', self::BURKINA_FASO_SELLER_USER_ID)->first();

            if ($burkinaShop) {
                $this->shopLocation($burkinaShop, $africa, $burkinaFaso, $ouagadougou);
            }

            // Bobo-Dioulasso is seeded per the spec (a second Burkina Faso
            // city to pick from) but isn't assigned to a shop — only one
            // Burkina Faso branch was asked for.
            unset($boboDioulasso);
        } catch (Throwable $e) {
            $this->error($e);
        }
    }

    private function region(string $title, string $locale): Region
    {
        $region = Region::whereHas('translation', fn($q) => $q->where('locale', $locale)->where('title', $title))->first();

        if (!$region) {
            $region = Region::create(['active' => true]);
            $region->translations()->create(['title' => $title, 'locale' => $locale]);
            $this->command?->info("region: $title");
        }

        return $region;
    }

    private function country(Region $region, string $title, string $iso2, string $currencyTitle, string $locale): Country
    {
        $currencyId = Currency::where('title', $currencyTitle)->value('id');

        $country = Country::whereHas('translation', fn($q) => $q->where('locale', $locale)->where('title', $title))->first();

        if (!$country) {
            $country = Country::create([
                'region_id'   => $region->id,
                'code'        => $iso2,
                'active'      => true,
                'img'         => "https://flagcdn.com/h120/$iso2.png",
                'currency_id' => $currencyId,
            ]);
            $country->translations()->create(['title' => $title, 'locale' => $locale]);
            $this->command?->info("country: $title");
        } elseif (!$country->currency_id && $currencyId) {
            $country->update(['currency_id' => $currencyId]);
        }

        return $country;
    }

    private function city(Region $region, Country $country, string $title, string $locale): City
    {
        $city = City::whereHas('translation', fn($q) => $q->where('locale', $locale)->where('title', $title))->first();

        if (!$city) {
            $city = City::create([
                'active'     => true,
                'region_id'  => $region->id,
                'country_id' => $country->id,
            ]);
            $city->translations()->create(['title' => $title, 'locale' => $locale]);
            $this->command?->info("city: $title");
        }

        return $city;
    }

    private function shopLocation(Shop $shop, Region $region, Country $country, City $city): void
    {
        ShopLocation::updateOrCreate([
            'shop_id' => $shop->id,
            'city_id' => $city->id,
        ], [
            'region_id'  => $region->id,
            'country_id' => $country->id,
            'type'       => ShopLocation::PRODUCT,
        ]);
    }
}
