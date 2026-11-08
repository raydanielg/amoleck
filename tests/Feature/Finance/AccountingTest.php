<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\AccountingPeriod;
use Modules\Finance\Models\ChartOfAccount;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\JournalEntry;
use Modules\Finance\Services\AccountingService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private int $hq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first()->id;
        \Nwidart\Modules\Facades\Module::find('Finance')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'finance')->update(['status' => 'active']);
        AccountingPeriod::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        foreach ([['1000','Cash','asset'],['1200','AR','asset'],['2100','Refunds','liability'],['4000','Revenue','revenue'],['5000','Expenses','expense']] as [$c,$n,$t]) {
            ChartOfAccount::create(['code' => $c, 'name' => $n, 'type' => $t]);
        }
    }

    private function acct(string $code): int { return ChartOfAccount::where('code', $code)->firstOrFail()->id; }

    public function test_balanced_journal_posts()
    {
        $j = app(AccountingService::class)->postJournal('Test', [
            ['account_id' => $this->acct('1200'), 'direction' => 'debit', 'amount' => 10000],
            ['account_id' => $this->acct('4000'), 'direction' => 'credit', 'amount' => 10000],
        ], $this->admin->id);
        $this->assertMatchesRegularExpression('/^JV-\d{4}-\d{6}$/', $j->journal_number);
        $this->assertEquals('posted', $j->status);
        $this->assertEquals(2, $j->lines()->count());
    }

    public function test_unbalanced_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AccountingService::class)->postJournal('Bad', [
            ['account_id' => $this->acct('1200'), 'direction' => 'debit', 'amount' => 10000],
            ['account_id' => $this->acct('4000'), 'direction' => 'credit', 'amount' => 5000],
        ], $this->admin->id);
    }

    public function test_non_postable_account_rejected()
    {
        ChartOfAccount::where('code', '4000')->update(['posting' => false]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AccountingService::class)->postJournal('Bad', [
            ['account_id' => $this->acct('1200'), 'direction' => 'debit', 'amount' => 100],
            ['account_id' => $this->acct('4000'), 'direction' => 'credit', 'amount' => 100],
        ], $this->admin->id);
    }

    public function test_closed_period_rejects()
    {
        AccountingPeriod::first()->update(['status' => 'closed']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AccountingService::class)->postJournal('Bad', [
            ['account_id' => $this->acct('1200'), 'direction' => 'debit', 'amount' => 100],
            ['account_id' => $this->acct('4000'), 'direction' => 'credit', 'amount' => 100],
        ], $this->admin->id);
    }

    public function test_invoice_event_posting()
    {
        $j = app(AccountingService::class)->postEvent('invoice_issued', 1, 10000, $this->hq, $this->admin->id);
        $this->assertEquals(1200, $j->lines()->where('direction', 'debit')->first()->chart_of_account_id);
        // duplicate event blocked by unique(source, source_id)
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        app(AccountingService::class)->postEvent('invoice_issued', 1, 10000, $this->hq, $this->admin->id);
    }

    public function test_trial_balance_balanced()
    {
        $svc = app(AccountingService::class);
        $svc->postEvent('invoice_issued', 1, 10000, $this->hq, $this->admin->id);
        $svc->postEvent('payment_confirmed', 2, 4000, $this->hq, $this->admin->id);
        $tb = $svc->trialBalance($this->hq);
        $this->assertTrue($tb['balanced']);
        $this->assertEquals(14000, $tb['total_debit']);
        $this->assertEquals(14000, $tb['total_credit']);
    }

    public function test_income_statement()
    {
        $svc = app(AccountingService::class);
        $svc->postEvent('invoice_issued', 1, 10000, $this->hq, $this->admin->id);
        $exp = Expense::create(['expense_number' => 'EXP-1', 'branch_id' => $this->hq, 'category' => 'supplies', 'amount' => 2000, 'expense_date' => today(), 'status' => 'approved', 'created_by' => $this->admin->id]);
        $svc->postExpense($exp, $this->admin->id);
        $is = $svc->incomeStatement($this->hq);
        $this->assertEquals(10000, $is['revenue']);
        $this->assertEquals(2000, $is['expenses']);
        $this->assertEquals(8000, $is['net']);
    }

    public function test_unposted_journals_excluded()
    {
        JournalEntry::create(['journal_number' => 'JV-X', 'journal_date' => today(), 'accounting_period_id' => AccountingPeriod::first()->id, 'branch_id' => $this->hq, 'description' => 'draft', 'status' => 'draft', 'created_by' => $this->admin->id]);
        $tb = app(AccountingService::class)->trialBalance($this->hq);
        $this->assertEquals(0, $tb['total_debit']); // draft excluded
    }

    public function test_invalid_coa_type_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AccountingService::class)->createCoa(['code' => '9999', 'name' => 'X', 'type' => 'bogus']);
    }
}
