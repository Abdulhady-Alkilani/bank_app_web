<?php

namespace App\Console\Commands;

use App\Enums\EventType;
use App\Enums\LabelSource;
use App\Models\Label;
use App\Models\RiskScore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

#[Signature('ml:export-training-data {--from= : Only scores evaluated on/after this date (Y-m-d)} {--to= : Only scores evaluated on/before this date (Y-m-d, inclusive)} {--format=csv : Output format (csv only; parquet is not available in PHP)} {--output= : Output path (default storage/app/ml/training-<timestamp>.csv)}')]
#[Description('Export one training row per risk score (CSV only: parquet is not available in PHP) with stored features and the resolved is_fraud label (bank > investigation > analyst > customer)')]
class ExportTrainingData extends Command
{
    private const CHUNK = 500;

    /** Label source priority, strongest first. */
    private const LABEL_PRIORITY = [
        LabelSource::Bank,
        LabelSource::Investigation,
        LabelSource::Analyst,
        LabelSource::Customer,
    ];

    private const BASE_COLUMNS = [
        'id', 'evaluated_at', 'event_type', 'account_id', 'beneficiary_id', 'rule_score',
        'decision', 'used_fallback', 'model_probability',
    ];

    public function handle(): int
    {
        if (strtolower((string) $this->option('format')) !== 'csv') {
            $this->error('Only the csv format is supported (parquet is not available in PHP).');

            return self::INVALID;
        }

        $from = $this->parseDate($this->option('from'), false);
        $to = $this->parseDate($this->option('to'), true);
        if ($from === false || $to === false) {
            $this->error('Invalid date; use Y-m-d.');

            return self::INVALID;
        }

        $path = $this->option('output') ?: storage_path('app/ml/training-'.now()->format('Ymd-His').'.csv');
        File::ensureDirectoryExists(dirname($path));

        $featureKeys = $this->featureKeys($from, $to);

        $handle = fopen($path, 'w');
        fputcsv($handle, [...self::BASE_COLUMNS, ...$featureKeys, 'is_fraud']);

        $rows = 0;
        $this->query($from, $to)
            ->with(['transaction:id,account_id,beneficiary_id', 'event:id,account_id,event_type'])
            ->chunkById(self::CHUNK, function (Collection $scores) use ($handle, $featureKeys, &$rows) {
                $labels = $this->labelsFor($scores);

                foreach ($scores as $score) {
                    fputcsv($handle, $this->row($score, $featureKeys, $labels));
                    $rows++;
                }
            });

        fclose($handle);

        $this->info("Exported {$rows} rows to {$path}");

        return self::SUCCESS;
    }

    private function parseDate(mixed $value, bool $endOfDay): Carbon|false|null
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = Carbon::parse((string) $value);
        } catch (\Throwable) {
            return false;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * @return Builder<RiskScore>
     */
    private function query(?Carbon $from, ?Carbon $to)
    {
        return RiskScore::query()
            ->when($from, fn ($q) => $q->where('evaluated_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('evaluated_at', '<=', $to));
    }

    /**
     * Union of every feature key seen in the range (first chunked pass, so the header is stable).
     *
     * @return list<string>
     */
    private function featureKeys(?Carbon $from, ?Carbon $to): array
    {
        $keys = [];

        $this->query($from, $to)->select(['id', 'features'])->chunkById(self::CHUNK, function (Collection $scores) use (&$keys) {
            foreach ($scores as $score) {
                foreach (array_keys($score->features ?? []) as $key) {
                    $keys[$key] = true;
                }
            }
        });

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    /**
     * Strongest label per transaction / event of the chunk: ['t:<id>' | 'e:<id>' => bool].
     *
     * @param  Collection<int, RiskScore>  $scores
     * @return array<string, bool>
     */
    private function labelsFor(Collection $scores): array
    {
        $transactionIds = $scores->pluck('transaction_id')->filter()->unique()->values();
        $eventIds = $scores->pluck('event_id')->filter()->unique()->values();

        $labels = Label::query()
            ->where(fn ($q) => $q->whereIn('transaction_id', $transactionIds)->orWhereIn('event_id', $eventIds))
            ->orderBy('id')
            ->get(['transaction_id', 'event_id', 'is_fraud', 'source']);

        $resolved = [];
        $rank = [];
        foreach ($labels as $label) {
            $priority = array_search($label->source, self::LABEL_PRIORITY, true);
            $priority = $priority === false ? count(self::LABEL_PRIORITY) : $priority;
            $key = $label->transaction_id ? 't:'.$label->transaction_id : 'e:'.$label->event_id;

            // Lower rank wins; for equal rank the most recent label (later id) wins.
            if (! isset($rank[$key]) || $priority <= $rank[$key]) {
                $rank[$key] = $priority;
                $resolved[$key] = (bool) $label->is_fraud;
            }
        }

        return $resolved;
    }

    /**
     * @param  list<string>  $featureKeys
     * @param  array<string, bool>  $labels
     * @return list<string|int|float|null>
     */
    private function row(RiskScore $score, array $featureKeys, array $labels): array
    {
        $features = $score->features ?? [];
        $label = $score->transaction_id ? ($labels['t:'.$score->transaction_id] ?? null) : null;
        $label ??= $score->event_id ? ($labels['e:'.$score->event_id] ?? null) : null;

        return [
            $score->id,
            $score->evaluated_at?->toIso8601String(),
            $score->transaction_id ? EventType::Transfer->value : $score->event?->event_type?->value,
            $score->transaction?->account_id ?? $score->event?->account_id,
            $score->transaction?->beneficiary_id,
            $score->rule_score,
            $score->decision->value,
            (int) $score->used_fallback,
            $score->model_probability,
            ...array_map(fn (string $key) => $this->cell($features[$key] ?? null), $featureKeys),
            $label === null ? '' : (int) $label,
        ];
    }

    private function cell(mixed $value): string|int|float|null
    {
        return match (true) {
            is_bool($value) => (int) $value,
            is_array($value) => json_encode($value),
            default => $value,
        };
    }
}
