<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Invitation;
use App\Models\Language;
use App\Models\Service;
use App\Models\ServiceMaster;
use App\Models\ServiceTranslation;
use App\Models\Shop;
use App\Models\User;
use App\Services\UserServices\UserService;
use App\Services\UserServices\UserWalletService;
use App\Traits\Loggable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Seeds a real, bookable service catalog for the two DemoAfricaSeeder shops
 * (Cameroon/501, Burkina Faso/502) — categories, services, and the master
 * assignments a service actually needs to be bookable at all.
 *
 * "Bookable" here means more than a Service row existing: Service::scopeFilter()'s
 * has_master check requires an active ServiceMaster row, and that master
 * needs both an accepted Invitation (see Service::scopeFilter()'s
 * ->whereHas('serviceMaster.master.invitations', fn($q) => $q->where('status', 2)))
 * and at least one UserWorkingDay row (->has('serviceMaster.master.workingDays')).
 * None of the three existed anywhere in this codebase before this seeder —
 * confirmed by testing against a genuinely fresh `migrate:fresh --seed`,
 * not just this dev database's own accumulated state: an earlier task's
 * manual, ad-hoc investigation had invited user 112 to shop 501 directly
 * against this long-lived dev DB, which looked like a real seeded
 * relationship until a fresh install showed zero invitations for that user
 * at all. Both masters (the existing global "master" demo user for Cameroon,
 * and a new one seeded here for Burkina Faso) get the same treatment:
 * ensureInvitation() and ensureWorkingDays() (the latter reusing
 * UserService::createDefaultWorkingDays(), the same helper the real
 * user-creation flow already calls) for whichever is missing either.
 *
 * Deliberately leaves img null on every category/service — this demo
 * environment can't currently source verified-working image URLs (Unsplash
 * MCP isn't enabled this session, and unsplash.com itself is blocked by this
 * sandbox's own egress proxy, so a real photo URL can't even be fetched to
 * confirm it's correct, unlike flagcdn.com's guessable, parametric URL
 * scheme). PR #74's ImageWithFallBack fix means a null img renders the
 * fallback image cleanly rather than crashing — real images are a deliberate
 * follow-up once Unsplash access is sorted, not guessed at here.
 */
class DemoServiceCatalogSeeder extends Seeder
{
    use Loggable;

    // See UserSeeder — the existing global "master" demo user. Not actually
    // invited to any shop by any seeder before this one (see class docblock).
    private const CAMEROON_MASTER_USER_ID = 112;
    private const CAMEROON_SHOP_ID = 501;

    // New demo master for the Burkina Faso shop — mirrors UserSeeder's
    // "second demo seller" pattern (a dedicated user for the second demo
    // country rather than reusing the Cameroon master cross-country).
    private const BURKINA_FASO_MASTER_USER_ID = 116;
    private const BURKINA_FASO_SHOP_ID = 502;

    // Top-level (type=SERVICE) category => its sub_service children.
    private const CATEGORY_TREE = [
        'Hair Care'      => ['Haircut', 'Hair Coloring'],
        'Nail Care'      => ['Manicure', 'Pedicure'],
        'Spa & Massage'  => ['Massage Therapy', 'Facial Treatment'],
        'Makeup'         => ['Bridal Makeup', 'Everyday Makeup'],
        'Barbershop'     => ['Beard Trim', "Men's Haircut"],
        'Skin Care'      => ['Facial Cleansing', 'Skin Consultation'],
    ];

    // The 6 services every demo shop offers, keyed by the sub_service
    // category they belong to, with a nominal price/duration. Same set for
    // both shops, same reasoning as DemoAfricaSeeder's symmetric
    // DeliveryPrice treatment — a small, predictable, easy-to-browse catalog
    // rather than inventing asymmetric per-shop assortments.
    private const SERVICES = [
        ['category' => 'Haircut',          'price' => 15, 'interval' => 30, 'description' => 'A precision haircut tailored to your style.'],
        ['category' => 'Hair Coloring',    'price' => 45, 'interval' => 90, 'description' => 'Full color or highlights using professional-grade dye.'],
        ['category' => 'Manicure',         'price' => 20, 'interval' => 45, 'description' => 'Nail shaping, cuticle care, and polish of your choice.'],
        ['category' => 'Massage Therapy',  'price' => 35, 'interval' => 60, 'description' => 'A relaxing full-body massage to ease tension.'],
        ['category' => 'Beard Trim',       'price' => 10, 'interval' => 20, 'description' => 'Beard shaping and trim with a straight razor finish.'],
        ['category' => 'Bridal Makeup',    'price' => 60, 'interval' => 90, 'description' => 'Full bridal makeup application, trial included.'],
    ];

    public function run(): void
    {
        $locale = data_get(Language::where('default', 1)->first(), 'locale', 'en');

        $categoriesByTitle = $this->categories($locale);

        // Each shop is seeded in its own try/catch: a failure resolving the
        // Cameroon master must not silently take Burkina Faso's services
        // down with it (and vice versa) - the two previously shared one
        // catch around both, so one shop's exception skipped the other's
        // seeding entirely with nothing but a log line to show for it.
        $this->seedShop(self::CAMEROON_SHOP_ID, fn (Shop $shop) => $this->cameroonMaster($shop), $categoriesByTitle, $locale);
        $this->seedShop(self::BURKINA_FASO_SHOP_ID, fn (Shop $shop) => $this->burkinaFasoMaster($shop), $categoriesByTitle, $locale);
    }

    /**
     * @param array<string, Category> $categoriesByTitle
     */
    private function seedShop(int $shopId, \Closure $resolveMaster, array $categoriesByTitle, string $locale): void
    {
        try {
            $shop = Shop::find($shopId);

            if (!$shop) {
                $this->command?->warn("DemoServiceCatalogSeeder: shop $shopId not found, skipping (run DemoAfricaSeeder/UserSeeder first)");

                return;
            }

            $master = $resolveMaster($shop);
            $this->servicesForShop($shop, $master, $categoriesByTitle, $locale);
        } catch (Throwable $e) {
            // Printed to the console, not just logged - a seeder that
            // reports "Seeding database" with no visible error while
            // silently creating zero services is exactly the failure mode
            // this is fixing.
            $this->command?->error("DemoServiceCatalogSeeder: failed seeding shop $shopId - {$e->getMessage()}");
            $this->error($e);
        }
    }

    /**
     * @return array<string, Category> sub_service category title => Category
     */
    private function categories(string $locale): array
    {
        $leafCategories = [];

        foreach (self::CATEGORY_TREE as $parentTitle => $children) {
            $parent = $this->category($parentTitle, Category::SERVICE, null, $locale);

            foreach ($children as $childTitle) {
                $leafCategories[$childTitle] = $this->category($childTitle, Category::SUB_SERVICE, $parent, $locale);
            }
        }

        return $leafCategories;
    }

    private function category(string $title, int $type, ?Category $parent, string $locale): Category
    {
        $category = Category::whereHas('translation', fn($q) => $q->where('locale', $locale)->where('title', $title))
            ->where('type', $type)
            ->first();

        if (!$category) {
            $category = Category::create([
                'type'      => $type,
                // Not nullable (default 0) — matches CategorySeeder's own
                // top-level rows, which likewise never set this explicitly.
                'parent_id' => $parent?->id ?? 0,
                'active'    => true,
                'status'    => Category::PUBLISHED,
                'img'       => null,
            ]);
            $category->translations()->create(['title' => $title, 'locale' => $locale]);
            $this->command?->info("category: $title");
        }

        return $category;
    }

    private function cameroonMaster(Shop $shop): User
    {
        $master = User::find(self::CAMEROON_MASTER_USER_ID);

        if (!$master) {
            // A plain User::find() miss used to be passed straight into
            // ensureInvitation()'s non-nullable User $master param, throwing
            // a bare "Call to a member function invitations() on null" -
            // caught by the (now-removed) shared try/catch with no
            // indication of which precondition actually failed.
            throw new RuntimeException(
                'Demo master user ' . self::CAMEROON_MASTER_USER_ID . ' not found - did UserSeeder run?'
            );
        }

        $this->ensureInvitation($master, $shop);
        $this->ensureWorkingDays($master);

        return $master;
    }

    private function burkinaFasoMaster(Shop $shop): User
    {
        $master = User::updateOrCreate([
            'id' => self::BURKINA_FASO_MASTER_USER_ID,
        ], [
            'uuid'              => Str::uuid(),
            'firstname'         => 'master-bf',
            'lastname'          => 'master-bf',
            'email'             => 'master-bf@githubit.com',
            'phone'             => '998911902698',
            'birthday'          => '1990-12-31',
            'gender'            => 'male',
            'email_verified_at' => now(),
            'password'          => bcrypt('masterbf'),
        ]);
        $master->syncRoles('master');

        (new UserWalletService)->create($master);

        $this->ensureInvitation($master, $shop);
        $this->ensureWorkingDays($master);
        $this->command?->info("master: {$master->email} (shop {$shop->id})");

        return $master;
    }

    private function ensureInvitation(User $master, Shop $shop): void
    {
        Invitation::updateOrCreate([
            'shop_id' => $shop->id,
            'user_id' => $master->id,
        ], [
            'role'   => 'master',
            'status' => Invitation::ACCEPTED,
        ]);
    }

    // Reuses UserService::createDefaultWorkingDays() — the same method
    // UserService::create() already calls automatically for every user
    // made through dashboard/seller/users (Master invitations, and the
    // Staff "create new account" path) — rather than duplicating its
    // Monday-Sunday/9:00-18:00 definition here with our own, slightly
    // different one.
    private function ensureWorkingDays(User $master): void
    {
        if ($master->workingDays()->exists()) {
            return;
        }

        (new UserService)->createDefaultWorkingDays($master);
    }

    /**
     * @param array<string, Category> $categoriesByTitle
     */
    private function servicesForShop(Shop $shop, User $master, array $categoriesByTitle, string $locale): void
    {
        foreach (self::SERVICES as $definition) {
            $category = $categoriesByTitle[$definition['category']];

            $service = Service::where('shop_id', $shop->id)
                ->where('category_id', $category->id)
                ->first();

            if (!$service) {
                $service = Service::create([
                    'category_id' => $category->id,
                    'shop_id'     => $shop->id,
                    'status'      => Service::STATUS_ACCEPTED,
                    'img'         => null,
                    'price'       => $definition['price'],
                    'interval'    => $definition['interval'],
                    'pause'       => 10,
                ]);
                ServiceTranslation::create([
                    'service_id'  => $service->id,
                    'locale'      => $locale,
                    'title'       => $definition['category'],
                    'description' => $definition['description'],
                ]);
                $this->command?->info("service: {$definition['category']} (shop {$shop->id})");
            }

            ServiceMaster::updateOrCreate([
                'service_id' => $service->id,
                'master_id'  => $master->id,
                'shop_id'    => $shop->id,
            ], [
                'active'   => true,
                'price'    => $definition['price'],
                'interval' => $definition['interval'],
                'pause'    => 10,
            ]);
        }
    }
}
