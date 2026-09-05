<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills sensible defaults for the two schema additions in the two
 * preceding migrations:
 *
 *  1. Every country with no `currency_id` set gets the platform's current
 *     default currency (there is no other per-country signal to use).
 *  2. Every active country gets a `country_payments` row for every currently
 *     active payment gateway, so no active country is left with zero
 *     available gateways after this migration.
 *
 * Two backup tables record exactly which countries/rows this migration
 * touched, so `down()` reverses precisely those — not any currency
 * assignment or country_payments row an admin sets afterwards through the
 * new country settings screen.
 */
return new class extends Migration {
    private string $currencyBackupTable = 'country_currency_backfill_backup';
    private string $paymentsBackupTable = 'country_payments_backfill_backup';

    public function up(): void
    {
        if (!Schema::hasTable($this->currencyBackupTable)) {
            Schema::create($this->currencyBackupTable, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('country_id');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable($this->paymentsBackupTable)) {
            Schema::create($this->paymentsBackupTable, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('country_payment_id');
                $table->timestamps();
            });
        }

        DB::transaction(function () {
            $defaultCurrencyId = DB::table('currencies')->where('default', 1)->value('id');

            if ($defaultCurrencyId) {
                $countryIds = DB::table('countries')->whereNull('currency_id')->pluck('id');

                foreach ($countryIds as $countryId) {
                    DB::table($this->currencyBackupTable)->insert([
                        'country_id' => $countryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($countryIds->isNotEmpty()) {
                    DB::table('countries')->whereIn('id', $countryIds)->update([
                        'currency_id' => $defaultCurrencyId,
                    ]);
                }
            }

            $activeCountryIds = DB::table('countries')->where('active', true)->pluck('id');
            $activePaymentIds = DB::table('payments')->where('active', true)->pluck('id');

            foreach ($activeCountryIds as $countryId) {
                foreach ($activePaymentIds as $paymentId) {
                    $exists = DB::table('country_payments')
                        ->where('country_id', $countryId)
                        ->where('payment_id', $paymentId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $id = DB::table('country_payments')->insertGetId([
                        'country_id' => $countryId,
                        'payment_id' => $paymentId,
                        'active'     => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table($this->paymentsBackupTable)->insert([
                        'country_payment_id' => $id,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable($this->paymentsBackupTable)) {
            $ids = DB::table($this->paymentsBackupTable)->pluck('country_payment_id');
            DB::table('country_payments')->whereIn('id', $ids)->delete();
            Schema::dropIfExists($this->paymentsBackupTable);
        }

        if (Schema::hasTable($this->currencyBackupTable)) {
            $ids = DB::table($this->currencyBackupTable)->pluck('country_id');
            DB::table('countries')->whereIn('id', $ids)->update(['currency_id' => null]);
            Schema::dropIfExists($this->currencyBackupTable);
        }
    }
};
