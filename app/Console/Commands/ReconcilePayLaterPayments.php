<?php

namespace App\Console\Commands;

use App\Models\CreditPaymentAttempt;
use App\PayLaterPayments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('paylater:reconcile {--limit=100 : Maximum attempts to check}')]
#[Description('Verify saved Pay Later payments with PayMongo without webhooks')]
class ReconcilePayLaterPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PayLaterPayments $payments): int
    {
        $attempts = CreditPaymentAttempt::query()->where(function ($query) {
            $query->whereIn('status', ['creating', 'pending'])
                ->orWhere(fn ($query) => $query->where('status', 'review')->whereNotNull('paymongo_checkout_session_id'));
        })
            ->orderBy('last_checked_at')->orderBy('id')
            ->limit(max(1, min(1000, (int) $this->option('limit'))))->get();
        $errors = 0;
        foreach ($attempts as $attempt) {
            try {
                $payments->sync($attempt);
            } catch (Throwable) {
                $attempt->update(['last_checked_at' => now()]);
                Log::warning('Pay Later payment verification unavailable.', ['attempt_id' => $attempt->id]);
                $errors++;
            }
        }
        $this->info('Checked '.$attempts->count().' payment attempts; '.$errors.' unavailable.');

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
