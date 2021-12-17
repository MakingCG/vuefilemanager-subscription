<?php
namespace App\Scheduler;

use VueFileManager\Subscription\Domain\Usage\Actions\SumUsageForCurrentPeriodAction;
use VueFileManager\Subscription\Domain\Subscriptions\Models\Subscription;
use VueFileManager\Subscription\Domain\Credits\Exceptions\InsufficientBalanceException;
use VueFileManager\Subscription\Domain\Credits\Notifications\InsufficientBalanceNotification;

class SettlePrePaidSubscriptionPeriodSchedule
{
    public function __construct(
        public SumUsageForCurrentPeriodAction $sumUsageForCurrentPeriod
    ) {
    }

    public function __invoke()
    {
        Subscription::where('type', 'pre-paid')
            ->where('status', 'active')
            ->whereDate('renews_at', today())
            ->cursor()
            ->each(function ($subscription) {
                // Get usage estimates
                $usageEstimates = ($this->sumUsageForCurrentPeriod)($subscription);

                try {
                    // Make withdrawal
                    $subscription->user->withdrawBalance($usageEstimates->sum('amount'));

                    // Create transaction
                    $subscription->user->transactions()->create([
                        'type'     => 'withdrawal',
                        'status'   => 'completed',
                        'note'     => now()->format('d. M') . ' - ' . now()->subDays(30)->format('d. M'),
                        'currency' => $subscription->plan->currency,
                        'amount'   => $usageEstimates->sum('amount'),
                        'driver'   => 'system',
                    ]);
                } catch (InsufficientBalanceException $e) {
                    // Notify user
                    $subscription->user->notify(new InsufficientBalanceNotification());

                    // Create error transaction
                    $transaction = $subscription->user->transactions()->create([
                        'type'     => 'withdrawal',
                        'status'   => 'error',
                        'note'     => now()->format('d. M') . ' - ' . now()->subDays(30)->format('d. M'),
                        'currency' => $subscription->plan->currency,
                        'amount'   => $usageEstimates->sum('amount'),
                        'driver'   => 'system',
                    ]);

                    // Store debt record
                    $subscription->user->debts()->create([
                        'currency'       => $subscription->plan->currency,
                        'amount'         => $usageEstimates->sum('amount'),
                        'transaction_id' => $transaction->id,
                    ]);
                }

                // Update next subscription period date
                $subscription->update([
                    'renews_at' => now()->addDays(30),
                ]);

                // Reset alert
                if ($subscription->user->billingAlert()->exists()) {
                    $subscription->user->billingAlert->update([
                        'triggered' => false,
                    ]);
                }
            });
    }
}
