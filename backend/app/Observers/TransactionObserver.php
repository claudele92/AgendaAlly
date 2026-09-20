<?php
declare(strict_types=1);

namespace App\Observers;

use App\Models\Booking;
use App\Models\PlatformFeeLedgerEntry;
use App\Models\Transaction;

/**
 * Records what the platform is owed the moment a payment actually
 * completes, regardless of which gateway carried it — see
 * PlatformFeeLedgerEntry. Every current "paid" code path (gateway
 * callback in BaseService::afterHook, the synchronous wallet debit in
 * TransactionService::walletHistoryAdd) works by calling
 * Transaction::update(['status' => ...]), so this is the one place
 * that catches all of them without duplicating the check into each
 * gateway service.
 */
class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        $this->recordFeeIfNewlyPaid($transaction);
    }

    private function recordFeeIfNewlyPaid(Transaction $transaction): void
    {
        if ($transaction->status !== Transaction::STATUS_PAID) {
            return;
        }

        // Only the progress -> paid transition is a real payment event;
        // any other update to an already-paid transaction (e.g. a later
        // refund flips it away and back, or an unrelated field changes)
        // must not create a second ledger entry for the same money.
        if ($transaction->getOriginal('status') === Transaction::STATUS_PAID) {
            return;
        }

        if ($transaction->payable_type !== Booking::class) {
            return;
        }

        /** @var Booking|null $booking */
        $booking = $transaction->payable;

        if (!$booking || (float) $booking->service_fee <= 0) {
            return;
        }

        PlatformFeeLedgerEntry::query()->firstOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'payable_type' => Booking::class,
                'payable_id'   => $booking->id,
                'shop_id'      => $booking->shop_id,
                'payment_id'   => $transaction->payment_sys_id,
                'currency_id'  => $booking->currency_id,
                'amount'       => $booking->service_fee,
                'status'       => PlatformFeeLedgerEntry::STATUS_PENDING,
            ]
        );
    }
}
