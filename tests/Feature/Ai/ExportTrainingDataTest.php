<?php

use App\Enums\LabelSource;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\Label;
use App\Models\RiskScore;
use App\Models\Transaction;

/**
 * @return list<array<string, string>>
 */
function readExport(string $path): array
{
    $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
    $header = array_shift($rows);

    return array_map(fn (array $row) => array_combine($header, $row), $rows);
}

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/training-'.uniqid().'.csv';
});

afterEach(function () {
    @unlink($this->path);
});

it('exports one row per risk score with feature columns and label priority bank over analyst over customer', function () {
    $beneficiary = Beneficiary::factory()->create();
    $tx = Transaction::factory()->create(['beneficiary_id' => $beneficiary->id]);
    $score = RiskScore::factory()->create(['transaction_id' => $tx->id, 'features' => ['amount' => 500, 'is_new_device' => true], 'rule_score' => 30, 'model_probability' => 0.2, 'used_fallback' => false]);
    Label::create(['transaction_id' => $tx->id, 'is_fraud' => true, 'source' => LabelSource::Customer]);
    Label::create(['transaction_id' => $tx->id, 'is_fraud' => true, 'source' => LabelSource::Analyst]);
    Label::create(['transaction_id' => $tx->id, 'is_fraud' => false, 'source' => LabelSource::Bank]);

    $event = AccountEvent::factory()->create();
    $eventScore = RiskScore::factory()->create(['transaction_id' => null, 'event_id' => $event->id, 'features' => ['amount' => 0, 'hour_of_day' => 3]]);
    Label::create(['event_id' => $event->id, 'is_fraud' => true, 'source' => LabelSource::Analyst]);
    Label::create(['event_id' => $event->id, 'is_fraud' => false, 'source' => LabelSource::Customer]);

    $unlabeled = RiskScore::factory()->create(['features' => ['amount' => 1]]);

    $this->artisan('ml:export-training-data', ['--output' => $this->path])->assertSuccessful();

    $rows = collect(readExport($this->path))->keyBy('id');
    expect($rows)->toHaveCount(3)
        ->and($rows[$score->id])->toMatchArray([
            'account_id' => (string) $tx->account_id,
            'beneficiary_id' => (string) $beneficiary->id,
            'event_type' => 'transfer',
            'rule_score' => '30',
            'decision' => 'allow',
            'used_fallback' => '0',
            'amount' => '500',
            'is_new_device' => '1',
            'hour_of_day' => '',
            'is_fraud' => '0',
        ])
        ->and($rows[$eventScore->id])->toMatchArray(['event_type' => $event->event_type->value, 'account_id' => (string) $event->account_id, 'beneficiary_id' => '', 'hour_of_day' => '3', 'is_fraud' => '1'])
        ->and($rows[$unlabeled->id]['is_fraud'])->toBe('');
});

it('filters by date range and rejects unsupported formats', function () {
    RiskScore::factory()->create(['evaluated_at' => '2026-01-10 12:00:00']);
    $recent = RiskScore::factory()->create(['evaluated_at' => '2026-03-05 12:00:00']);

    $this->artisan('ml:export-training-data', ['--from' => '2026-03-01', '--to' => '2026-03-05', '--output' => $this->path])->assertSuccessful();
    expect(collect(readExport($this->path))->pluck('id')->all())->toBe([(string) $recent->id]);

    $this->artisan('ml:export-training-data', ['--format' => 'parquet', '--output' => $this->path])->assertFailed();
});
