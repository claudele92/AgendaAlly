<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a second kind of row to the existing fee ledger rather than a new
 * table: 'payable' rows track what the platform owes a shop that opted
 * into collect_via_platform (it received the full charge on the shop's
 * behalf), and 'payable_adjustment' rows model a booking's one-time
 * cancellation/refund as a new signed row instead of mutating the
 * already-settled 'payable'/'fee' entries. The original
 * unique(transaction_id) - one 'fee' row per transaction - becomes
 * unique(transaction_id, entry_type) so a 'payable' row and, later, one
 * 'payable_adjustment' row can each coexist alongside the 'fee' row
 * already written for the same transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_ledger_entries', function (Blueprint $table) {
            $table->string('entry_type', 24)->default('fee')->after('shop_id');
            $table->dropUnique(['transaction_id']);
        });

        Schema::table('platform_fee_ledger_entries', function (Blueprint $table) {
            $table->unique(['transaction_id', 'entry_type']);
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_ledger_entries', function (Blueprint $table) {
            $table->dropUnique(['transaction_id', 'entry_type']);
        });

        Schema::table('platform_fee_ledger_entries', function (Blueprint $table) {
            $table->unique('transaction_id');
            $table->dropColumn('entry_type');
        });
    }
};
