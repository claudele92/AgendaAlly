<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Notification;
use App\Models\Shop;
use App\Models\ShopTag;
use App\Models\ShopTranslation;
use App\Models\User;
use App\Services\UserServices\UserWalletService;
use App\Traits\Loggable;
use App\Traits\SetTranslations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Throwable;

class UserSeeder extends Seeder
{
    use Loggable;
    // Only for its setSlug() helper — these two shops are created via raw
    // Shop::updateOrCreate() below rather than through ShopService::create(),
    // so they never go through that service's own setTranslations() call
    // (which is what generates a slug for every shop made through the real
    // seller-facing flow). Reusing the exact same helper instead of
    // reimplementing its Str::slug($title) . "-$id" formula a second time.
    use SetTranslations;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $users = [
            [
                'id' => 102,
                'uuid' => Str::uuid(),
                'firstname' => 'User',
                'lastname' => 'User',
                'email' => 'user@githubit.com',
                'phone' => '998911902595',
                'birthday' => '1993-12-30',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('user123'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 103,
                'uuid' => Str::uuid(),
                'firstname' => 'Owner',
                'lastname' => 'Owner',
                'email' => 'owner@githubit.com',
                'phone' => '998911902696',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('githubit'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 104,
                'uuid' => Str::uuid(),
                'firstname' => 'Manager',
                'lastname' => 'Manager',
                'email' => 'manager@githubit.com',
                'phone' => '998911902616',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('manager'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 105,
                'uuid' => Str::uuid(),
                'firstname' => 'Moderator',
                'lastname' => 'Moderator',
                'email' => 'moderator@githubit.com',
                'phone' => '998911902116',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('moderator'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 106,
                'uuid' => Str::uuid(),
                'firstname' => 'Delivery',
                'lastname' => 'Delivery',
                'email' => 'delivery@githubit.com',
                'phone' => '998911912116',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('delivery'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 107,
                'uuid' => Str::uuid(),
                'firstname' => 'sellers',
                'lastname' => 'sellers',
                'email' => 'sellers@githubit.com',
                'phone' => '998911902691',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('seller'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 112,
                'uuid' => Str::uuid(),
                // Real-sounding Cameroonian name (Fotso is a common
                // Bamileke surname) - was the literal 'master'/'master'
                // placeholder. Email/password kept stable since they're
                // used as demo login credentials elsewhere.
                'firstname' => 'Armand',
                'lastname' => 'Fotso',
                'email' => 'master@githubit.com',
                'phone' => '998911902694',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('master'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Second demo seller, based in Burkina Faso — see
            // DemoAfricaSeeder for the country/city/ShopLocation data that
            // actually places this shop there.
            [
                'id' => 113,
                'uuid' => Str::uuid(),
                'firstname' => 'sellers-bf',
                'lastname' => 'sellers-bf',
                'email' => 'sellers-bf@githubit.com',
                'phone' => '998911902692',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('sellerbf'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Demo staff member for shop 501 (Cameroon) — see
            // DemoStaffInvitationSeeder, which assigns this user the
            // "Branch Manager" shop_role at the Douala branch.
            [
                'id' => 114,
                'uuid' => Str::uuid(),
                'firstname' => 'Branch',
                'lastname' => 'Manager',
                'email' => 'branch-manager@githubit.com',
                'phone' => '998911902695',
                'birthday' => '1990-12-31',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => bcrypt('branchmanager'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Demo platform-wide finance staff — see DemoCountryInvitationSeeder,
            // which assigns this user the "Main Accountant" country_role
            // (country_id = NULL on the role itself) via a Cameroon-scoped
            // CountryInvitation, since every actual grant still needs a
            // real country_id on the invitation row.
            [
                'id' => 115,
                'uuid' => Str::uuid(),
                'firstname' => 'Main',
                'lastname' => 'Accountant',
                'email' => 'main-accountant@githubit.com',
                'phone' => '998911902697',
                'birthday' => '1990-12-31',
                'gender' => 'female',
                'email_verified_at' => now(),
                'password' => bcrypt('accountant'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($users as $user) {

            try {
                $user = User::updateOrCreate(['id' => data_get($user, 'id')], $user);

                (new UserWalletService)->create($user);

                $id = Notification::where('type', Notification::PUSH)
                    ->select(['id', 'type'])
                    ->first()
                    ?->id;

                $user->notifications()->sync([$id]);
            } catch (Throwable $e) {
                $this->error($e);
            }

        }

        User::find(102)?->syncRoles('user');
        User::find(103)?->syncRoles(['admin']);
        User::find(107)?->syncRoles('seller');
        User::find(104)?->syncRoles('manager');
        User::find(105)?->syncRoles('moderator');
        User::find(106)?->syncRoles('deliveryman');
        User::find(113)?->syncRoles('seller');
        User::find(112)?->syncRoles('master');

        $shop = Shop::updateOrCreate([
            'user_id'           => 107,
        ], [
            'uuid'              => Str::uuid(),
            // Real Douala, Cameroon coordinates — the previous values
            // (-69.3453324, 69.3453324) placed this shop in the Southern
            // Ocean near Antarctica, thousands of km from the country/city
            // (Cameroon/Douala) DemoAfricaSeeder actually assigns it via
            // ShopLocation. That mismatch was silently masked until the
            // storefront search's location filter was fixed to actually
            // apply country_id/city_id — before that, every distance
            // calculation involving this shop was already meaningless.
            'latitude'          => 4.0511,
            'longitude'         => 9.7679,
            'phone'             => '+1234566',
            'open'              => 1,
            // Placeholder demo imagery — placehold.co isn't in web/'s
            // next.config.js images.remotePatterns allowlist, so it crashed
            // next/image (and the old 'url.webp' value before that wasn't a
            // resolvable path at all either). flagcdn.com IS already
            // allowlisted there and already used elsewhere in this exact
            // codebase for country flags (see DemoAfricaSeeder::country()),
            // so reusing it here needs no config change and no new external
            // dependency — just this shop's own country's flag standing in
            // for a real uploaded logo/banner.
            'background_img'    => 'https://flagcdn.com/h240/cm.png',
            'logo_img'          => 'https://flagcdn.com/h120/cm.png',
            'status'            => 'approved',
            'status_note'       => 'approved',
            'delivery_time'     => [
                'from'              => '10',
                'to'                => '90',
                'type'              => 'minute',
            ],
            'type'              => 1,
        ]);

        $shopLocale = data_get(Language::first(), 'locale', 'en');

        ShopTranslation::updateOrCreate([
            'shop_id'       => $shop->id,
        ], [
            // Real-sounding Douala, Cameroon salon name/description/address
            // - was the literal 'branch title'/'branch desc'/'address'
            // placeholder.
            'description'   => 'A modern beauty studio in the heart of Douala, offering hair, nail, and spa services for the whole family.',
            'title'         => 'Le Sawa Beauty Studio',
            'locale'        => $shopLocale,
            'address'       => '12 Rue de la Joie, Bonanjo, Douala, Cameroon',
        ]);

        // No !$shop->slug guard here: setSlug() is a deterministic
        // Str::slug($title)-$id, so re-running it is a safe no-op once the
        // title stops changing, and it needs to actually run once now to
        // pick up the new name above (an existing shop already has a slug
        // from the old placeholder title).
        try {
            $this->setSlug($shop, [$shopLocale => 'Le Sawa Beauty Studio'], $shopLocale);
        } catch (Throwable $e) {
            $this->error($e);
        }

        $shop->tags()->sync(ShopTag::pluck('id')->toArray());

        // Second demo shop, for the Burkina Faso seller — DemoAfricaSeeder
        // gives it its ShopLocation (which is what actually determines its
        // checkout country; see Shop::checkoutCountry()).
        $bfShop = Shop::updateOrCreate([
            'user_id'           => 113,
        ], [
            'uuid'              => Str::uuid(),
            'latitude'          => 12.3714277,
            'longitude'         => -1.5196603,
            'phone'             => '+2267000000',
            'open'              => 1,
            // Same flagcdn.com fix as the Cameroon shop above, this
            // country's own flag ('bf') standing in for a real logo/banner.
            'background_img'    => 'https://flagcdn.com/h240/bf.png',
            'logo_img'          => 'https://flagcdn.com/h120/bf.png',
            'status'            => 'approved',
            'status_note'       => 'approved',
            'delivery_time'     => [
                'from'              => '10',
                'to'                => '90',
                'type'              => 'minute',
            ],
            'type'              => 1,
        ]);

        ShopTranslation::updateOrCreate([
            'shop_id'       => $bfShop->id,
        ], [
            // Real-sounding Ouagadougou, Burkina Faso salon name/description
            // /address - was the generic 'Ouagadougou branch'/'Ouagadougou
            // branch desc' placeholder (Kwame Nkrumah Avenue is a real,
            // well-known avenue in central Ouagadougou).
            'description'   => 'A welcoming beauty salon in central Ouagadougou, offering hair, nail, and skin care for every occasion.',
            'title'         => 'Ouaga Éclat Beauté',
            'locale'        => $shopLocale,
            'address'       => 'Avenue Kwame Nkrumah, Ouagadougou, Burkina Faso',
        ]);

        // Same reasoning as the Cameroon shop above: always re-run so an
        // existing shop's slug picks up the new title, not just a
        // never-yet-slugged one.
        try {
            $this->setSlug($bfShop, [$shopLocale => 'Ouaga Éclat Beauté'], $shopLocale);
        } catch (Throwable $e) {
            $this->error($e);
        }

        $bfShop->tags()->sync(ShopTag::pluck('id')->toArray());

    }

}
