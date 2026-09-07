<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Traits\Loggable;
use Illuminate\Database\Seeder;
use Throwable;

class CurrencySeeder extends Seeder
{
    use Loggable;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $currencies = [
            [
                'id' => 2,
                'symbol' => '$',
                'title' => 'USD',
                'rate' => 1.0,
                'default' => 1,
                'active' => 1,
            ],
            // Demo data for the Cameroon/Burkina Faso seed (DemoAfricaSeeder)
            // — two distinct CFA franc zones, pegged to each other but not
            // interchangeable currencies. Rate is an approximate demo
            // value (~600 per USD), not a live FX rate. No hardcoded id:
            // unlike USD above there's no existing reference to preserve,
            // so keying by title avoids any risk of id collisions.
            [
                'symbol' => 'FCFA',
                'title' => 'XAF',
                'rate' => 600.0,
                'default' => 0,
                'active' => 1,
            ],
            [
                'symbol' => 'FCFA',
                'title' => 'XOF',
                'rate' => 600.0,
                'default' => 0,
                'active' => 1,
            ],
            // Additional demo currencies — rates are approximate demo
            // values against USD (the default), not live/maintained FX
            // rates, same caveat as XAF/XOF above.
            [
                'symbol' => '€',
                'title' => 'EUR',
                'rate' => 0.92,
                'default' => 0,
                'active' => 1,
            ],
            [
                'symbol' => '₦',
                'title' => 'NGN',
                'rate' => 1550.0,
                'default' => 0,
                'active' => 1,
            ],
            [
                'symbol' => 'GH₵',
                'title' => 'GHS',
                'rate' => 15.0,
                'default' => 0,
                'active' => 1,
            ],
            [
                'symbol' => 'CA$',
                'title' => 'CAD',
                'rate' => 1.37,
                'default' => 0,
                'active' => 1,
            ],
            [
                'symbol' => '£',
                'title' => 'GBP',
                'rate' => 0.79,
                'default' => 0,
                'active' => 1,
            ],
            [
                'symbol' => 'FC',
                'title' => 'CDF',
                'rate' => 2870.0,
                'default' => 0,
                'active' => 1,
            ],
            [
                'symbol' => 'KSh',
                'title' => 'KES',
                'rate' => 129.0,
                'default' => 0,
                'active' => 1,
            ],
        ];

        foreach ($currencies as $currency) {
            $key = array_key_exists('id', $currency) ? ['id' => $currency['id']] : ['title' => $currency['title']];

            try {
                Currency::updateOrCreate($key, $currency);
            } catch (Throwable $e) {
                $this->error($e);
            }
        }

    }
}
