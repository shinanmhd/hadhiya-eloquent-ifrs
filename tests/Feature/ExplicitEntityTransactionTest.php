<?php

namespace IFRS\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use IFRS\Context\EntityContext;
use IFRS\Models\Account;
use IFRS\Models\Balance;
use IFRS\Models\Ledger;
use IFRS\Models\LineItem;
use IFRS\Models\Transaction;
use IFRS\Tests\TestCase;
use IFRS\Transactions\JournalEntry;

class ExplicitEntityTransactionTest extends TestCase
{
    public function testJournalPostsForExplicitEntityWithoutAuthentication(): void
    {
        $entity = Auth::user()->entity;
        $mainAccount = factory(Account::class)->create([
            'category_id' => null,
        ]);
        $lineAccount = factory(Account::class)->create([
            'category_id' => null,
        ]);

        Auth::logout();

        $this->app->make(EntityContext::class)->runForEntity(
            $entity,
            function () use ($entity, $mainAccount, $lineAccount): void {
                $journal = new JournalEntry([
                    'account_id' => $mainAccount->id,
                    'transaction_date' => Carbon::now(),
                    'narration' => $this->faker->word,
                ]);
                $journal->addLineItem(new LineItem([
                    'account_id' => $lineAccount->id,
                    'amount' => 100,
                    'quantity' => 1,
                ]));

                $journal->post();

                $this->assertSame($entity->id, $journal->entity_id);
                $this->assertSame($entity->currency_id, $journal->currency_id);
                $this->assertSame($entity->default_rate->id, $journal->exchange_rate_id);
                $this->assertStringStartsWith(Transaction::JN, $journal->transaction_no);
                $this->assertCount(2, Ledger::where('transaction_id', $journal->id)->get());
                $this->assertSame(
                    [Balance::CREDIT, Balance::DEBIT],
                    Ledger::where('transaction_id', $journal->id)
                        ->orderBy('entry_type')
                        ->pluck('entry_type')
                        ->all()
                );
            }
        );
    }
}
