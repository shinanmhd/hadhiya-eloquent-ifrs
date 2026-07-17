<?php

namespace IFRS\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use IFRS\Context\EntityContext;
use IFRS\Models\Account;
use IFRS\Reports\AgingSchedule;
use IFRS\Reports\BalanceSheet;
use IFRS\Reports\IncomeStatement;
use IFRS\Tests\TestCase;

class ExplicitEntityReportTest extends TestCase
{
    public function testReportsResolveActiveEntityWithoutAuthentication(): void
    {
        $entity = Auth::user()->entity;
        Auth::logout();

        $this->app->make(EntityContext::class)->runForEntity($entity, function () use ($entity): void {
            $balanceSheet = new BalanceSheet();
            $agingSchedule = new AgingSchedule(Account::RECEIVABLE);
            $results = IncomeStatement::getResults(date('m'), date('Y'));

            $this->assertSame($entity->name, $balanceSheet->attributes()['Entity']);
            $this->assertSame($entity->name, $agingSchedule->attributes()->Entity);
            $this->assertArrayHasKey(IncomeStatement::NET_PROFIT, $results);
        });
    }
}
