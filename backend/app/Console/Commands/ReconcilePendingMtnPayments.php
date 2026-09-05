<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentProcess;
use App\Models\ShopPayment;
use App\Models\Transaction;
use App\Services\PaymentService\MtnService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Log;
use Throwable;

/**
 * MTN's webhook delivery isn't guaranteed — a successful payment whose
 * callback never arrives would otherwise sit unrecorded indefinitely.
 * This actively polls MTN's own status endpoint for every request-to-pay
 * still unresolved after a few minutes, using the same
 * MtnService::checkStatus()/BaseService::afterHook() path the webhook
 * itself uses, so a late poll and an on-time webhook land identically.
 *
 * Requests still unresolved after 24h are treated as failed locally —
 * MTN's own sandbox/production request-to-pay requests don't stay
 * PENDING indefinitely in practice, so this is a safety cutoff against
 * polling a genuinely abandoned transaction forever, not an expectation
 * that legitimate transactions take anywhere near that long.
 */
class ReconcilePendingMtnPayments extends Command
{
    protected $signature = 'mtn:reconcile-pending-payments';

    protected $description = 'Poll MTN for the status of request-to-pay transactions whose webhook has not arrived';

    private const MIN_AGE_MINUTES = 2;
    private const MAX_AGE_HOURS   = 24;

    public function handle(MtnService $service): int
    {
        $payment = Payment::query()->where('tag', Payment::TAG_MTN)->first();

        if (!$payment) {
            return 0;
        }

        $candidates = PaymentProcess::query()
            ->where('data->payment_id', $payment->id)
            ->get()
            ->filter(fn (PaymentProcess $process) => data_get($process->data, 'mtn_reference_id')
                && !data_get($process->data, 'mtn_resolved'));

        foreach ($candidates as $process) {
            $this->reconcileOne($process, $service);
        }

        return 0;
    }

    private function reconcileOne(PaymentProcess $process, MtnService $service): void
    {
        $requestedAt = data_get($process->data, 'requested_at');
        $ageMinutes  = $requestedAt ? Carbon::parse($requestedAt)->diffInMinutes(now()) : null;

        if ($ageMinutes === null || $ageMinutes < self::MIN_AGE_MINUTES) {
            return;
        }

        $referenceId = data_get($process->data, 'mtn_reference_id');

        try {
            if ($ageMinutes >= self::MAX_AGE_HOURS * 60) {
                $service->afterHook($referenceId, Transaction::STATUS_CANCELED);
                $this->markResolved($process->refresh());
                return;
            }

            $shopId      = $service->resolveGatewayShopId($process->model_type, $process->model_id);
            $shopPayment = ShopPayment::forShopAndPayment($shopId, data_get($process->data, 'payment_id'));

            if (!$shopPayment) {
                return;
            }

            $result = $service->checkStatus($shopPayment, $referenceId);

            $status = match ($result['status'] ?? null) {
                'FAILED'     => Transaction::STATUS_CANCELED,
                'SUCCESSFUL' => Transaction::STATUS_PAID,
                default      => null,
            };

            if ($status === null) {
                return;
            }

            $service->afterHook($referenceId, $status);
            $this->markResolved($process->refresh());
        } catch (Throwable $e) {
            Log::error('mtn:reconcile-pending-payments', [
                'reference_id' => $referenceId,
                'message'      => $e->getMessage(),
            ]);
        }
    }

    private function markResolved(PaymentProcess $process): void
    {
        $process->update([
            'data' => array_merge($process->data, ['mtn_resolved' => true]),
        ]);
    }
}
