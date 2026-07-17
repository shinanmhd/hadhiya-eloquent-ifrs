<?php

namespace IFRS\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use IFRS\Context\EntityContext;
use IFRS\Models\Account;
use IFRS\Models\Balance;
use IFRS\Models\Currency;
use IFRS\Models\Entity;
use IFRS\Models\RecycledObject;
use IFRS\Models\ReportingPeriod;
use IFRS\Models\Transaction;
use IFRS\Tests\TestCase;

class ExplicitEntityModelTest extends TestCase
{
    public function testAccountAndCurrencyDefaultsResolveWithoutAuthentication(): void
    {
        $entity = Auth::user()->entity;
        Auth::logout();

        $this->app->make(EntityContext::class)->runForEntity($entity, function () use ($entity): void {
            $currency = Currency::create([
                'name' => 'Context Currency',
                'currency_code' => 'CTX',
            ]);
            $account = Account::create([
                'name' => 'Context Account',
                'account_type' => Account::BANK,
                'category_id' => null,
            ]);

            $this->assertSame($entity->id, $currency->entity_id);
            $this->assertSame($entity->id, $account->entity_id);
            $this->assertSame($entity->currency_id, $account->currency_id);
        });
    }

    public function testReportingPeriodHelpersResolveWithoutAuthentication(): void
    {
        $entity = Auth::user()->entity;
        Auth::logout();

        $this->app->make(EntityContext::class)->runForEntity($entity, function () use ($entity): void {
            $period = ReportingPeriod::getPeriod(Carbon::now());

            $this->assertSame($entity->id, $period->entity_id);
            $this->assertSame($entity->year_start, ReportingPeriod::periodStart()->month);
        });
    }

    public function testBalanceDefaultsResolveWithoutAuthentication(): void
    {
        $entity = Auth::user()->entity;
        $account = factory(Account::class)->create([
            'account_type' => Account::INVENTORY,
            'category_id' => null,
        ]);
        Auth::logout();

        $this->app->make(EntityContext::class)->runForEntity($entity, function () use ($entity, $account): void {
            $balance = Balance::create([
                'account_id' => $account->id,
                'transaction_type' => Transaction::JN,
                'transaction_date' => Carbon::now()->subYear(),
                'reference' => 'context-balance',
                'balance_type' => Balance::DEBIT,
                'balance' => 100,
            ]);

            $this->assertSame($entity->id, $balance->entity_id);
            $this->assertSame($entity->current_reporting_period->id, $balance->reporting_period_id);
            $this->assertSame($entity->default_rate->id, $balance->exchange_rate_id);
        });
    }

    public function testRecyclingUsesActiveEntityInsteadOfAuthenticatedUsersEntity(): void
    {
        $authenticatedUser = Auth::user();
        $entity = factory(Entity::class)->create();

        $this->app->make(EntityContext::class)->runForEntity($entity, function () use ($entity, $authenticatedUser): void {
            $currency = Currency::create([
                'name' => 'Second Entity Currency',
                'currency_code' => 'SEC',
                'entity_id' => $entity->id,
            ]);
            $entity->currency_id = $currency->id;
            $entity->save();
            ReportingPeriod::create([
                'calendar_year' => date('Y'),
                'period_count' => 1,
            ]);

            $account = Account::create([
                'name' => 'Second Entity Account',
                'account_type' => Account::BANK,
                'category_id' => null,
            ]);
            $account->delete();

            $recycled = RecycledObject::where('recyclable_id', $account->id)->firstOrFail();

            $this->assertSame($entity->id, $recycled->entity_id);
            $this->assertSame($authenticatedUser->id, $recycled->user_id);
        });
    }
}
