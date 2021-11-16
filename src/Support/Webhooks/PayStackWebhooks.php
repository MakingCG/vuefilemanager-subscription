<?php

namespace VueFileManager\Subscription\Support\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Models\User;
use VueFileManager\Subscription\Domain\Plans\Models\PlanDriver;
use VueFileManager\Subscription\Domain\Customers\Models\Customer;
use VueFileManager\Subscription\Support\EngineManager;
use VueFileManager\Subscription\Support\Events\SubscriptionWasCreated;
use VueFileManager\Subscription\Support\Events\SubscriptionWasCancelled;
use VueFileManager\Subscription\Domain\Subscriptions\Models\Subscription;
use VueFileManager\Subscription\Domain\Subscriptions\Models\SubscriptionDriver;
use VueFileManager\Subscription\Support\Events\SubscriptionWasUpdated;

class PayStackWebhooks
{
    public function handleSubscriptionCreate(Request $request): void
    {
        $customerCode = $request->input('data.customer.customer_code');
        $customerEmail = $request->input('data.customer.email');
        $subscriptionCode = $request->input('data.subscription_code');
        $planCode = $request->input('data.plan.plan_code');

        // Get existing customer
        $customer = Customer::where('driver', 'paystack')
            ->where('driver_user_id', $customerCode)
            ->first();

        if (!$customer) {

            // Get user by email
            $user = User::where('email', $customerEmail)
                ->first();

            // Store customer id to the database
            $user->customer()
                ->create([
                    'driver_user_id' => $customerCode,
                    'driver'         => 'paystack',
                ]);

            $customer = $user->customer;
        }

        $planDriver = PlanDriver::where('driver_plan_id', $planCode)
            ->first();

        // Replace existing subscription
        if ($customer->user->subscription && ($customer->user->subscription->driverId() !== $subscriptionCode)) {

            // Cancel previous subscription
            resolve(EngineManager::class)
                ->driver('paystack')
                ->cancelSubscription(
                    $customer->user->subscription
                );

            // Update subscription
            $customer->user->subscription()->update([
                'plan_id'       => $planDriver->plan->id,
                'name'          => $planDriver->plan->name,
                'status'        => 'active',
                'ends_at'       => null,
                'trial_ends_at' => null,
            ]);

            // Update subscription driver id
            $customer->user->subscription->driver()->update([
                'driver_subscription_id' => $subscriptionCode,
            ]);

            // Refresh subscription data
            $customer->user->subscription->refresh();

            // Emit SubscriptionWasUpdated
            SubscriptionWasUpdated::dispatch($customer->user->subscription);
        }

        // Create new subscription
        if (!$customer->user->subscription) {

            $subscription = Subscription::create([
                'plan_id' => $planDriver->plan->id,
                'user_id' => $customer->user_id,
                'name'    => $planDriver->plan->name,
            ]);

            // Store subscription pivot to gateway
            $subscription
                ->driver()
                ->create([
                    'driver'                 => 'paystack',
                    'driver_subscription_id' => $subscriptionCode,
                ]);

            // Emit SubscriptionWasCreated
            SubscriptionWasCreated::dispatch($subscription);
        }
    }

    public function handleSubscriptionNotRenew(Request $request): void
    {
        $subscriptionCode = $request->input('data.subscription_code');
        $endsAt = $request->input('data.cancelledAt');

        $driver = SubscriptionDriver::where('driver_subscription_id', $subscriptionCode)
            ->first();

        if ($driver->subscription->active()) {
            $driver->subscription->update([
                'status'  => 'cancelled',
                'ends_at' => $endsAt,
            ]);

            SubscriptionWasCancelled::dispatch($driver->subscription);
        }
    }
}
