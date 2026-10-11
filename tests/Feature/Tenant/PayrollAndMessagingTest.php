<?php

declare(strict_types=1);

use App\Actions\Payroll\PayrollActions;
use App\Actions\Periods\PeriodActions;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Filament\Tenant\Resources\Advances\Pages\ManageAdvances;
use App\Filament\Tenant\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Tenant\Resources\Visitors\Pages\ManageVisitors;
use App\Models\Advance;
use App\Models\Announcement;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\NotificationLog;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Notifications\Channels\HttpSmsChannel;
use App\Services\Notifications\Channels\MetaWhatsAppChannel;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use App\Services\Settings\TenantSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function setting(string $key, mixed $value): void
{
    Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    app(TenantSettings::class)->flush();
}

beforeEach(function () {
    setting('feature.payroll_lite', true);
    $this->owner = User::where('email', 'owner@pest.test')->first();
    $this->emp = Employee::create(['code' => 'PAY1', 'first_name' => 'Bilal', 'joined_on' => '2025-01-01', 'phone' => '03211234567']);
    // August 2026: 26 present days (2 of them late ×3 → 1 rollup day), 1 absent, 4 weekly offs
    foreach (range(1, 31) as $d) {
        $date = sprintf('2026-08-%02d', $d);
        $status = match (true) {
            date('w', strtotime($date)) === '0' => AttendanceStatus::WeeklyOff,
            $d === 12 => AttendanceStatus::Absent,
            default => AttendanceStatus::Present,
        };
        AttendanceDay::create(['employee_id' => $this->emp->id, 'date' => $date, 'status' => $status,
            'flags' => in_array($d, [3, 4, 5], true) ? ['late'] : [], 'overtime_min' => $d === 20 ? 120 : 0]);
    }
    SalaryStructure::create(['employee_id' => $this->emp->id, 'effective_from' => '2026-01-01', 'basic' => 50000,
        'allowances' => [['name' => 'House rent', 'amount' => 12000]], 'deductions_fixed' => [['name' => 'EOBI', 'amount' => 370]],
        'overtime_rate_normal' => 400, 'bank_name' => 'HBL', 'bank_account' => 'PK36HABB0000001234567890']);
    Advance::create(['employee_id' => $this->emp->id, 'amount' => 9000, 'instalments' => 3, 'remaining' => 9000, 'issued_on' => '2026-07-15', 'status' => 'active']);
});

it('refuses payroll for an unlocked month', function () {
    $period = app(PeriodActions::class)->period(2026, 8);

    app(PayrollActions::class)->generate($period, $this->owner);
})->throws(ValidationException::class);

it('generates payslips from the locked snapshot and finalises them', function () {
    Storage::fake('local');
    $periods = app(PeriodActions::class);
    $period = $periods->period(2026, 8);
    $periods->lock($period, $this->owner);

    $payroll = app(PayrollActions::class);
    $run = $payroll->generate($period->fresh(), $this->owner);
    $slip = $run->payslips()->where('employee_id', $this->emp->id)->first();

    // per day = 62,000 / 31 = 2,000; deduction days = 1 absent + 1 late rollup = 2 → 4,000
    $labels = collect($slip->lines)->pluck('amount', 'label');
    expect($slip->gross)->toBe(62000 + 800) // 2 h OT × 400
        ->and($labels->first(fn ($v, $k) => str_starts_with($k, 'Attendance deduction')))->toBe(4000)
        ->and($labels['EOBI'])->toBe(370)
        ->and($labels->first(fn ($v, $k) => str_starts_with($k, 'Advance recovery')))->toBe(3000)
        ->and($slip->net)->toBe(62800 - 4000 - 370 - 3000);

    $payroll->finalise($run->fresh(), $this->owner);
    expect($run->fresh()->status)->toBe('final')
        ->and(Advance::first()->remaining)->toBe(6000)
        ->and($slip->fresh()->pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($slip->fresh()->pdf_path))->toBeTrue()
        ->and(NotificationLog::where('event', 'payslip_ready')->exists())->toBeTrue()
        ->and(fn () => $payroll->generate($period->fresh(), $this->owner))->toThrow(ValidationException::class);

    $csv = $payroll->bankCsv($run->fresh());
    expect($csv)->toContain('PK36HABB0000001234567890')->toContain((string) (62800 - 4000 - 370 - 3000));

    // A name Excel would run as a formula is written as text.
    $this->emp->update(['first_name' => '=HYPERLINK("http://evil.test")']);
    expect($payroll->bankCsv($run->fresh()))->toContain("\"'=HYPERLINK(")->not->toContain(',=HYPERLINK(');
});

it('takes the bank details for the bank file from the salary structure of the run month', function () {
    $periods = app(PeriodActions::class);
    $period = $periods->period(2026, 8);
    $periods->lock($period, $this->owner);
    $payroll = app(PayrollActions::class);
    $run = $payroll->generate($period->fresh(), $this->owner);
    SalaryStructure::create(['employee_id' => $this->emp->id, 'effective_from' => now()->subDay()->format('Y-m-d'), 'basic' => 70000,
        'bank_name' => 'MCB', 'bank_account' => 'PK00MUCB0000009999999999']);

    expect($payroll->bankCsv($run->fresh()))->toContain('PK36HABB0000001234567890')->not->toContain('PK00MUCB');
});

it('downloads the payslip PDFs of a final run, one or all in a ZIP', function () {
    Storage::fake('local');
    $periods = app(PeriodActions::class);
    $period = $periods->period(2026, 8);
    $periods->lock($period, $this->owner);
    $payroll = app(PayrollActions::class);
    $run = $payroll->finalise($payroll->generate($period->fresh(), $this->owner)->fresh(), $this->owner);
    $slip = $run->payslips()->firstOrFail();
    Storage::disk('local')->delete($slip->pdf_path); // a lost file is made again

    expect(substr($payroll->payslipPdf($slip), 0, 4))->toBe('%PDF');
    $zip = new ZipArchive;
    $zip->open($payroll->payslipsZip($run));
    expect($zip->numFiles)->toBe(1)->and($zip->getNameIndex(0))->toBe('PAY1 - Bilal - Payslip August 2026.pdf');

    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs($this->owner);
    Livewire::test(ListPayrollRuns::class)
        ->callTableAction('payslips', $run, data: ['payslip' => (string) $slip->id])
        ->assertFileDownloaded('PAY1 - Bilal - Payslip August 2026.pdf');
});

it('lets HR change the amount of an advance or delete it until something is recovered', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs($this->owner);
    $advance = Advance::firstOrFail();

    Livewire::test(ManageAdvances::class)
        ->callTableAction('edit', $advance, data: ['amount' => 12000, 'instalments' => 4])
        ->assertHasNoTableActionErrors();
    expect($advance->fresh()->only(['amount', 'remaining']))->toBe(['amount' => 12000, 'remaining' => 12000]);

    $advance->refresh()->update(['remaining' => 9000]); // one instalment recovered
    Livewire::test(ManageAdvances::class)->assertTableActionHidden('delete', $advance);

    $advance->update(['remaining' => 12000]);
    Livewire::test(ManageAdvances::class)->callTableAction('delete', $advance);
    expect(Advance::count())->toBe(0);
});

it('stores the bank account encrypted', function () {
    $raw = DB::table('salary_structures')->value('bank_account');

    expect($raw)->not->toContain('PK36HABB')->and(SalaryStructure::first()->bank_account)->toBe('PK36HABB0000001234567890');
});

it('sends WhatsApp template messages through the Meta Cloud API', function () {
    setting('whatsapp_meta_token', Crypt::encryptString('EAAG-token'));
    setting('whatsapp_meta_phone_number_id', '1234567890');
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

    $r = app(MetaWhatsAppChannel::class)->send(new Recipient('Bilal', null, '0321-1234567'),
        new RenderedMessage('leave_decided', 'x', 'y', ['type' => 'Casual', 'from' => '1 Sep', 'to' => '2 Sep', 'status' => 'approved', 'note' => ''], 'leave_decided_v1'));

    expect($r->ok)->toBeTrue()->and($r->providerRef)->toBe('wamid.ABC');
    Http::assertSent(fn ($req) => $req->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
        && $req->hasHeader('Authorization', 'Bearer EAAG-token')
        && $req['to'] === '923211234567'
        && $req['template']['name'] === 'leave_decided_v1'
        && $req['template']['components'][0]['parameters'][0]['text'] === 'Casual');
});

it('reports WhatsApp API errors as failed deliveries', function () {
    setting('whatsapp_meta_token', Crypt::encryptString('bad'));
    setting('whatsapp_meta_phone_number_id', '1');
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);

    $r = app(MetaWhatsAppChannel::class)->send(new Recipient('B', null, '03211234567'), new RenderedMessage('test', null, 'hi'));

    expect($r->ok)->toBeFalse()->and($r->error)->toContain('Invalid OAuth');
});

it('sends SMS through a configurable HTTP gateway', function () {
    setting('sms_http_url', 'https://203.0.113.10/api/send'); // literal public address: passes the SSRF guard without DNS
    setting('sms_http_method', 'GET');
    setting('sms_http_params', ['key' => '{api_key}', 'receiver' => '{to}', 'msgdata' => '{message}', 'mask' => '{sender}']);
    setting('sms_http_api_key', Crypt::encryptString('k-123'));
    setting('sms_http_sender', 'ACME');
    setting('sms_http_success_contains', 'Message Sent');
    Http::fake(['203.0.113.10/*' => Http::response('Message Sent Successfully')]);

    $r = app(HttpSmsChannel::class)->send(new Recipient('B', null, '03211234567'), new RenderedMessage('test', null, 'Hello'));

    expect($r->ok)->toBeTrue();
    Http::assertSent(fn ($req) => str_contains($req->url(), 'receiver=923211234567') && str_contains($req->url(), 'key=k-123') && str_contains($req->url(), 'mask=ACME'));
});

it('numbers visitor badges per day', function () {
    Visitor::create(['name' => 'A', 'checked_in_at' => now(), 'badge_no' => Visitor::nextBadge()]);

    expect(Visitor::nextBadge())->toBe('V002');
});

it('lets reception edit a visitor entry and only HR and up delete one, without two visitors sharing a badge', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    Setting::updateOrCreate(['key' => 'feature.visitors'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $first = Visitor::create(['name' => 'Ali', 'checked_in_at' => now(), 'badge_no' => Visitor::nextBadge()]);
    $typo = Visitor::create(['name' => 'Jhon', 'checked_in_at' => now(), 'badge_no' => Visitor::nextBadge()]);

    $reception = User::create(['name' => 'Desk', 'email' => 'desk-v@pest.test', 'password' => 'x']);
    $reception->assignRole(Role::Receptionist->value);
    $this->actingAs($reception);
    Livewire::test(ManageVisitors::class)
        ->callTableAction('edit', $typo, data: ['name' => 'John'])->assertHasNoTableActionErrors()
        ->assertTableActionHidden('delete', $typo);
    expect($typo->fresh()->name)->toBe('John');

    $hr = User::create(['name' => 'HR', 'email' => 'hr-v@pest.test', 'password' => 'x']);
    $hr->assignRole(Role::Hr->value);
    $this->actingAs($hr);
    Livewire::test(ManageVisitors::class)->assertCanSeeTableRecords([$first, $typo])->callTableAction('delete', $first);

    expect(Visitor::find($first->id))->toBeNull()
        ->and(Visitor::nextBadge())->toBe('V003');
});

it('broadcasts announcements to their audience only', function () {
    $other = Branch::create(['name' => 'Other', 'code' => 'OTH']);
    $a = Announcement::create(['title' => 'Only Other', 'body' => 'x', 'audience' => ['branch_ids' => [$other->id]]]);

    expect($a->isFor($this->emp))->toBeFalse()
        ->and($a->isFor(Employee::create(['code' => 'OB', 'first_name' => 'O', 'joined_on' => '2025-01-01', 'branch_id' => $other->id])))->toBeTrue();
});
