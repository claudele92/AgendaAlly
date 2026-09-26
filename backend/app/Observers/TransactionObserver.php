<?php
declare(strict_types=1);

namespace App\Observers;

use App\Models\Booking;
use App\Models\PlatformFeeLedgerEntry;
use App\Models\Transaction;

/**
 * Records both directions of the fee ledger the moment a payment actually
 * completes, regardless of which gateway carried it — see
 * PlatformFeeLedgerEntry. Every current "paid" code path (gateway
 * callback in BaseService::afterHook, the synchronous wallet debit in
 * TransactionService::walletHistoryAdd) works by calling
 * Transaction::update(['status' => ...]), so this is the one place
 * that catches all of them without duplicating the check into each
 * gateway service: what the platform is owed (its service fee, always),
 * and what the platform now owes the shop back (only when the shop opted
 * into collect_via_platform, so the platform's own gateway - not the
 * shop's - actually received the money).
 */
class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        if (!$this->isNewlyPaidBookingTransaction($transaction)) {
            return;
        }

        /** @var Booking $booking */
        $booking = $transaction->payable;

        $this->recordFee($transaction, $booking);
        $this->recordPayableIfCollectingOnBehalfOfShop($transaction, $booking);
    }

    private function isNewlyPaidBookingTransaction(Transaction $transaction): bool
    {
        if ($transaction->status !== Transaction::STATUS_PAID) {
            return false;
        }

        // Only the progress -> paid transition is a real payment event;
        // any other update to an already-paid transaction (e.g. a later
        // refund flips it away and back, or an unrelated field changes)
        // must not create a second ledger entry for the same money.
        if ($transaction->getOriginal('status') === Transaction::STATUS_PAID) {
            return false;
        }

        if ($transaction->payable_type !== Booking::class) {
            return false;
        }

        return (bool) $transaction->payable;
    }

    private function recordFee(Transaction $transaction, Booking $booking): void
    {
        if ((float) $booking->service_fee <= 0) {
            return;
        }

        PlatformFeeLedgerEntry::query()->firstOrCreate(
            ['transaction_id' => $transaction->id, 'entry_type' => PlatformFeeLedgerEntry::ENTRY_TYPE_FEE],
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

    /**
     * When a shop has opted into collect_via_platform, checkout already
     * routed this payment through the platform's own gateway (see
     * BaseService::resolveGatewayConfig()) rather than the shop's own
     * credentials - so the platform, not the shop, actually holds the
     * seller's share of this payment right now. Record that as a payable
     * ledger row, read once here at settlement and never recomputed if the
     * shop's toggle or fee settings change afterward (firstOrCreate never
     * updates an existing row's amount).
     */
    private function recordPayableIfCollectingOnBehalfOfShop(Transaction $transaction, Booking $booking): void
    {
        if (!$booking->shop?->collect_via_platform) {
            return;
        }

        $sellerFee = (float) $booking->seller_fee;

        if ($sellerFee <= 0) {
            return;
        }

        PlatformFeeLedgerEntry::query()->firstOrCreate(
            ['transaction_id' => $transaction->id, 'entry_type' => PlatformFeeLedgerEntry::ENTRY_TYPE_PAYABLE],
            [
                'payable_type' => Booking::class,
                'payable_id'   => $booking->id,
                'shop_id'      => $booking->shop_id,
                'payment_id'   => $transaction->payment_sys_id,
                'currency_id'  => $booking->currency_id,
                'amount'       => $sellerFee,
                'status'       => PlatformFeeLedgerEntry::STATUS_PENDING,
            ]
        );
    }
}
