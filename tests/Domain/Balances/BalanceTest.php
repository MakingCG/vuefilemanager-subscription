<?php
namespace Tests\Domain\Balances;

use Tests\TestCase;
use Tests\Models\User;
use Illuminate\Database\Eloquent\Model;
use VueFileManager\Subscription\Domain\Credits\Exceptions\InsufficientBalanceException;
use VueFileManager\Subscription\Domain\Credits\Models\Debt;
use VueFileManager\Subscription\Domain\Transactions\Models\Transaction;

class BalanceTest extends TestCase
{
    public Model $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()
            ->create();
    }

    /**
     * @test
     */
    public function it_credit_user_balance()
    {
        $this->user->creditBalance(50.00, 'USD');

        $this->assertDatabaseHas('balances', [
            'user_id'  => $this->user->id,
            'amount'   => 50.00,
            'currency' => 'USD',
        ]);
    }

    /**
     * @test
     */
    public function it_credit_balance_and_pay_user_debt()
    {
        $this->user->creditBalance(5.00, 'USD');

        $transaction = Transaction::factory()
            ->create([
                'user_id'  => $this->user->id,
                'amount'   => 10.25,
                'currency' => 'USD',
                'type'     => 'withdrawal',
                'status'   => 'error',
            ]);

        Debt::factory()
            ->create([
                'transaction_id' => $transaction->id,
                'user_id'        => $this->user->id,
                'amount'         => 10.25,
                'currency'       => 'USD',
            ]);

        $this->user->refresh();

        $this->user->creditBalance(50.00, 'USD');

        $this
            ->assertDatabaseHas('balances', [
                'user_id'  => $this->user->id,
                'amount'   => 44.75,
                'currency' => 'USD',
            ])
            ->assertDatabaseHas('transactions', [
                'user_id'  => $this->user->id,
                'type'     => 'withdrawal',
                'status'   => 'completed',
                'currency' => 'USD',
                'amount'   => 10.25,
            ])
            ->assertDatabaseCount('debts', 0);
    }

    /**
     * @test
     */
    public function it_increment_user_balance()
    {
        $this->user->balance()->create([
            'currency' => 'USD',
            'amount'   => 50.00,
        ]);

        $this->user->creditBalance(10.49);

        $this->assertDatabaseHas('balances', [
            'user_id' => $this->user->id,
            'amount'  => 60.49,
        ]);
    }

    /**
     * @test
     */
    public function it_withdraw_user_balance()
    {
        $this->user->balance()->create([
            'currency' => 'USD',
            'amount'   => 50.00,
        ]);

        $this->user->withdrawBalance(10.49);

        $this->assertDatabaseHas('balances', [
            'user_id' => $this->user->id,
            'amount'  => 39.51,
        ]);
    }

    /**
     * @test
     */
    public function it_try_to_withdraw_more_than_current_user_balance()
    {
        $this->expectException(InsufficientBalanceException::class);

        $this->user->balance()->create([
            'currency' => 'USD',
            'amount'   => 10.00,
        ]);

        $this->user->withdrawBalance(20.00);
    }
}
