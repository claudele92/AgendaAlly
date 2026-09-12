<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $items = [
            [
                'key'   => 'is_demo',
                'value' => true
            ]
        ];

        foreach ($items as $item) {
            Settings::updateOrCreate([
                'key'   => data_get($item, 'key'),
            ], [
                'value' => data_get($item, 'value')
            ]);
        }

        // Real hero/footer copy for the storefront (web/components/footer)
        // and platform lat/long (used as the search page's fallback
        // "where am I" center when no explicit address is set - see
        // shops.tsx/marker-cluster.tsx). Previously blank, which made that
        // fallback resolve to (0,0) ("null island") instead of anywhere
        // near the demo shops. firstOrCreate, same reasoning as
        // google_map_key below: once a superadmin edits these via the UI,
        // a later reseed must not silently overwrite their value.
        $firstOrCreateItems = [
            'title'       => 'AgendaAlly',
            'description' => 'Book beauty and wellness appointments near you.',
            'footer_text' => '© ' . date('Y') . ' AgendaAlly. All rights reserved.',
            // Douala, Cameroon - same coordinates as the Cameroon demo shop
            // (see UserSeeder), a reasonable "platform home base" default
            // given this seed's demo geography is Cameroon/Burkina Faso.
            'latitude'    => '4.0511',
            'longitude'   => '9.7679',
        ];

        foreach ($firstOrCreateItems as $key => $value) {
            Settings::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        // google_map_key is read by admin/, web/ and the mobile app straight off
        // this same key/value settings table (no dedicated column - see
        // Settings::class) to init their Google Maps SDK/geocoding widgets; the
        // backend itself never calls Google's API with it. Historically this
        // row only ever got created by a superadmin filling in Settings ->
        // General Settings -> Location, which means it's blank on every fresh
        // deploy until someone remembers to re-enter it by hand.
        //
        // Set GOOGLE_MAP_KEY in this app's real .env (there's no backend
        // .env.example to add it to - this comment is the documentation) to
        // have a fresh `db:seed` populate it automatically. Deliberately
        // firstOrCreate, not updateOrCreate like the loop above: once a
        // superadmin has edited this via the UI, a later reseed must not
        // silently overwrite their value back to the env var (or blank it,
        // if the env var isn't set on that later run).
        Settings::firstOrCreate([
            'key' => 'google_map_key',
        ], [
            'value' => env('GOOGLE_MAP_KEY', ''),
        ]);
    }
}
