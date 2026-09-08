<?php

declare(strict_types=1);

namespace Tests\Feature\Currency;

use App\Models\AdsPackage;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Region;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopLocation;
use App\Models\Stock;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request as RequestFacade;
use Tests\TestCase;

/**
 * Phase 1 of the multi-currency work: a shared Currency::convert() helper,
 * and extending it (for Stock/Service/AdsPackage/Subscription only — Order/
 * Booking/Cart/ParcelOrder already have their own, separate, frozen-rate
 * conversion for the customer-facing paths, untouched here) to the seller/
 * moderator-facing dashboard paths, using the shop's own resolved currency
 * rather than a customer's explicit preference or the platform default.
 */
class SellerCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSellerRequest(): void
    {
        RequestFacade::swap(\Illuminate\Http\Request::create('/api/v1/dashboard/seller/products/paginate', 'GET'));
    }

    private function fakeAdminRequest(): void
    {
        RequestFacade::swap(\Illuminate\Http\Request::create('/api/v1/dashboard/admin/products/paginate', 'GET'));
    }

    /** @return array{0: Shop, 1: Currency, 2: User} */
    private function makeShopWithCountryCurrency(string $currencyTitle, float $rate): array
    {
        $currency = Currency::query()->create(['title' => $currencyTitle, 'symbol' => $currencyTitle, 'rate' => $rate, 'default' => 0, 'active' => 1]);
        $region   = Region::query()->create(['active' => true]);
        $country  = Country::query()->create(['region_id' => $region->id, 'active' => true, 'currency_id' => $currency->id]);

        $user = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id'       => $user->id,
            'type'          => 1,
            'delivery_time' => ['type' => 'minute', 'from' => 30, 'to' => 60],
        ]);

        ShopLocation::query()->create([
            'shop_id'    => $shop->id,
            'region_id'  => $region->id,
            'country_id' => $country->id,
            'type'       => ShopLocation::PRODUCT,
        ]);

        return [$shop, $currency, $user];
    }

    public function test_currency_convert_is_a_no_op_when_the_target_currency_is_unresolved(): void
    {
        Currency::query()->create(['title' => 'USD', 'symbol' => '$', 'rate' => 1, 'default' => 1, 'active' => 1]);

        $this->assertSame(250.0, Currency::convert(250.0, null));
        $this->assertSame(250.0, Currency::convert(250.0, 999999));
    }

    public function test_currency_convert_applies_the_usd_relative_rate_ratio(): void
    {
        Currency::query()->create(['title' => 'USD', 'symbol' => '$', 'rate' => 1, 'default' => 1, 'active' => 1]);
        $xaf = Currency::query()->create(['title' => 'XAF', 'symbol' => 'FCFA', 'rate' => 600, 'default' => 0, 'active' => 1]);

        $this->assertSame(150000.0, Currency::convert(250.0, $xaf->id));
    }

    public function test_stock_price_converts_to_the_products_own_shop_currency_on_seller_paths_only(): void
    {
        Currency::query()->create(['title' => 'USD', 'symbol' => '$', 'rate' => 1, 'default' => 1, 'active' => 1]);
        [$shop] = $this->makeShopWithCountryCurrency('XAF', 600);

        $category = Category::factory()->create();
        $product  = Product::factory()->create(['shop_id' => $shop->id, 'category_id' => $category->id]);
        $stock    = Stock::query()->create(['product_id' => $product->id, 'price' => 250, 'quantity' => 10]);

        $this->fakeSellerRequest();
        $this->assertSame(150000.0, $stock->fresh()->rate_price);

        $this->fakeAdminRequest();
        $this->assertSame(250.0, $stock->fresh()->rate_price);
    }

    public function test_service_price_converts_to_its_own_shop_currency_on_seller_paths_only(): void
    {
        Currency::query()->create(['title' => 'USD', 'symbol' => '$', 'rate' => 1, 'default' => 1, 'active' => 1]);
        [$shop] = $this->makeShopWithCountryCurrency('XOF', 600);

        $category = Category::factory()->create();
        $service  = Service::query()->create([
            'shop_id'     => $shop->id,
            'category_id' => $category->id,
            'price'       => 100,
        ]);

        $this->fakeSellerRequest();
        $this->assertSame(60000.0, $service->fresh()->rate_price);

        $this->fakeAdminRequest();
        $this->assertSame(100.0, $service->fresh()->rate_price);
    }

    public function test_subscription_price_converts_to_the_viewing_sellers_own_shop_currency(): void
    {
        Currency::query()->create(['title' => 'USD', 'symbol' => '$', 'rate' => 1, 'default' => 1, 'active' => 1]);
        [, , $user] = $this->makeShopWithCountryCurrency('XAF', 600);

        $subscription = Subscription::query()->create(['type' => 'shop', 'price' => 250, 'month' => 1, 'active' => true, 'title' => 'Starter']);

        $this->actingAs($user, 'sanctum');
        $this->fakeSellerRequest();
        $this->assertSame(150000.0, $subscription->fresh()->rate_price);

        $this->fakeAdminRequest();
        $this->assertSame(250.0, $subscription->fresh()->rate_price);
    }

    public function test_ads_package_price_converts_to_the_viewing_sellers_own_shop_currency(): void
    {
        Currency::query()->create(['title' => 'USD', 'symbol' => '$', 'rate' => 1, 'default' => 1, 'active' => 1]);
        [, , $user] = $this->makeShopWithCountryCurrency('XOF', 600);

        $adsPackage = AdsPackage::query()->create(['active' => true, 'type' => AdsPackage::MAIN, 'time_type' => 'day', 'time' => 1, 'price' => 100]);

        $this->actingAs($user, 'sanctum');
        $this->fakeSellerRequest();
        $this->assertSame(60000.0, $adsPackage->fresh()->rate_price);

        $this->fakeAdminRequest();
        $this->assertSame(100.0, $adsPackage->fresh()->rate_price);
    }
}
