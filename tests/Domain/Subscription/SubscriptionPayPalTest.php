<?php

namespace Tests\Domain\Subscription;

use Tests\TestCase;
use Tests\Models\User;
use Illuminate\Support\Facades\Http;
use VueFileManager\Subscription\Domain\Plans\Models\Plan;
use VueFileManager\Subscription\Domain\Subscriptions\Models\Subscription;
use VueFileManager\Subscription\Support\EngineManager;

class SubscriptionPayPalTest extends TestCase
{
    /**
     * @test
     */
    public function it_cancel_paypal_subscription()
    {
        $user = User::factory()
            ->create();

        $subscription = Subscription::factory()
            ->hasDriver([
                'driver' => 'paypal',
            ])
            ->create([
                'user_id'    => $user->id,
                'status'     => 'active',
                'ends_at'    => null,
                'created_at' => now()->subDays(14),
            ]);

        $ends_at = now()->addDays(14);

        $api = "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscription->driverId()}";

        Http::fake([
            "{$api}"        => Http::response([
                'id'                 => 'I-BW452GLLEP1G',
                'plan_id'            => 'P-5ML4271244454362WXNWU5NQ',
                'start_time'         => '2019-04-10T07:00:00Z',
                'quantity'           => '20',
                'shipping_amount'    => [
                    'currency_code' => 'USD',
                    'value'         => '10.0',
                ],
                'subscriber'         => [
                    'shipping_address' => [
                        'name'    => [
                            'full_name' => 'John Doe',
                        ],
                        'address' => [
                            'address_line_1' => '2211 N First Street',
                            'address_line_2' => 'Building 17',
                            'admin_area_2'   => 'San Jose',
                            'admin_area_1'   => 'CA',
                            'postal_code'    => '95131',
                            'country_code'   => 'US',
                        ],
                    ],
                    'name'             => [
                        'given_name' => 'John',
                        'surname'    => 'Doe',
                    ],
                    'email_address'    => 'customer@example.com',
                    'payer_id'         => '2J6QB8YJQSJRJ',
                ],
                'billing_info'       => [
                    'outstanding_balance'   => [
                        'currency_code' => 'USD',
                        'value'         => '1.0',
                    ],
                    'cycle_executions'      => [
                        [
                            'tenure_type'      => 'TRIAL',
                            'sequence'         => 1,
                            'cycles_completed' => 0,
                            'cycles_remaining' => 2,
                            'total_cycles'     => 2,
                        ],
                        [
                            'tenure_type'      => 'TRIAL',
                            'sequence'         => 2,
                            'cycles_completed' => 0,
                            'cycles_remaining' => 3,
                            'total_cycles'     => 3,
                        ],
                        [
                            'tenure_type'      => 'REGULAR',
                            'sequence'         => 3,
                            'cycles_completed' => 0,
                            'cycles_remaining' => 12,
                            'total_cycles'     => 12,
                        ],
                    ],
                    'last_payment'          => [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value'         => '1.15',
                        ],
                        'time'   => '2019-04-09T10:27:20Z',
                    ],
                    'next_billing_time'     => $ends_at,
                    'failed_payments_count' => 0,
                ],
                'create_time'        => '2019-04-09T10:26:04Z',
                'update_time'        => '2019-04-09T10:27:27Z',
                'links'              => [
                    [
                        'href'   => 'https://api-m.paypal.com/v1/billing/subscriptions/I-BW452GLLEP1G/cancel',
                        'rel'    => 'cancel',
                        'method' => 'POST',
                    ],
                    [
                        'href'   => 'https://api-m.paypal.com/v1/billing/subscriptions/I-BW452GLLEP1G',
                        'rel'    => 'edit',
                        'method' => 'PATCH',
                    ],
                    [
                        'href'   => 'https://api-m.paypal.com/v1/billing/subscriptions/I-BW452GLLEP1G',
                        'rel'    => 'self',
                        'method' => 'GET',
                    ],
                    [
                        'href'   => 'https://api-m.paypal.com/v1/billing/subscriptions/I-BW452GLLEP1G/suspend',
                        'rel'    => 'suspend',
                        'method' => 'POST',
                    ],
                    [
                        'href'   => 'https://api-m.paypal.com/v1/billing/subscriptions/I-BW452GLLEP1G/capture',
                        'rel'    => 'capture',
                        'method' => 'POST',
                    ],
                ],
                'status'             => 'ACTIVE',
                'status_update_time' => '2019-04-09T10:27:27Z',
            ]),
            "{$api}/cancel" => Http::response([], 204),
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/subscription/cancel')
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'cancelled',
            ]);

        $subscription->refresh();

        $this
            ->assertDatabaseHas('subscriptions', [
                'status'  => 'cancelled',
                'ends_at' => $ends_at,
            ])
            ->assertEquals(true, $subscription->onGracePeriod());
    }

    /**
     * @test
     */
    public function it_swap_paypal_subscription()
    {
        $user = User::factory()
            ->create();

        [$plan, $planHigher] = Plan::factory()
            ->hasDrivers([
                'driver' => 'paypal',
            ])
            ->count(2)
            ->create();

        $subscription = Subscription::factory()
            ->hasDriver([
                'driver' => 'paypal',
            ])
            ->create([
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'status'  => 'active',
            ]);

        Http::fake([
            "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscription->driverId()}/revise" => Http::response([
                "plan_id"         => $planHigher->driverId('paypal'),
                "plan_overridden" => false,
                "links"           => [
                    [
                        "href"   => "https://www.sandbox.paypal.com/webapps/billing/subscriptions/update?ba_token=BA-4CY05557UG442950B",
                        "rel"    => "approve",
                        "method" => "GET",
                    ]
                    , [
                        "href"   => "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-GW6LUW7CW1AC",
                        "rel"    => "edit",
                        "method" => "PATCH",
                    ]
                    , [
                        "href"   => "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-GW6LUW7CW1AC",
                        "rel"    => "self",
                        "method" => "GET",
                    ]
                    , [
                        "href"   => "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-GW6LUW7CW1AC/cancel",
                        "rel"    => "cancel",
                        "method" => "POST",
                    ]
                    , [
                        "href"   => "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-GW6LUW7CW1AC/suspend",
                        "rel"    => "suspend",
                        "method" => "POST",
                    ]
                    , [
                        "href"   => "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-GW6LUW7CW1AC/capture",
                        "rel"    => "capture",
                        "method" => "POST",
                    ]
                ]
            ]),
        ]);

        $this
            ->actingAs($user)
            ->postJson("/api/subscription/swap/{$planHigher->id}")
            ->assertOk()
            ->assertJsonFragment([
                'plan_id' => $planHigher->driverId('paypal'),
            ]);
    }
}
