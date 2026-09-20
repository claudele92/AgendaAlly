<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\PlatformFeeLedgerEntry
 *
 * One row per paid booking transaction that carried a service_fee —
 * see TransactionObserver, which creates these automatically. Not a
 * money movement itself, just the record of what the platform is owed
 * and whether it has actually been collected yet.
 *
 * @property int $id
 * @property string $payable_type
 * @property int $payable_id
 * @property int $shop_id
 * @property int $transaction_id
 * @property int|null $payment_id
 * @property int|null $currency_id
 * @property float $amount
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $collected_at
 * @property string|null $note
 * @property-read Booking|null $payable
 * @property-read Shop|null $shop
 * @property-read Transaction|null $transaction
 * @property-read Payment|null $payment
 * @property-read Currency|null $currency
 */
class PlatformFeeLedgerEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'collected_at' => 'datetime',
        'amount'       => 'float',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_COLLECTED = 'collected';
    const STATUS_WAIVED    = 'waived';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COLLECTED,
        self::STATUS_WAIVED,
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function markCollected(?string $note = null): bool
    {
        return $this->update([
            'status'       => self::STATUS_COLLECTED,
            'collected_at' => now(),
            'note'         => $note ?? $this->note,
        ]);
    }

    public function markWaived(?string $note = null): bool
    {
        return $this->update([
            'status'       => self::STATUS_WAIVED,
            'collected_at' => now(),
            'note'         => $note ?? $this->note,
        ]);
    }
}
