<?php

namespace App\Services\Admin;

use App\Contracts\AiScoringClient;
use App\Models\RiskScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthService
{
    /**
     * @return array<string, int> queue name => pending jobs
     */
    public function queueSizes(): array
    {
        if (! Schema::hasTable('jobs')) {
            return [];
        }

        return DB::table('jobs')->select('queue', DB::raw('count(*) as total'))->groupBy('queue')
            ->pluck('total', 'queue')->map(fn ($n) => (int) $n)->all();
    }

    public function queueBacklog(): int
    {
        return array_sum($this->queueSizes());
    }

    public function failedJobsCount(): int
    {
        return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
    }

    /**
     * @return array<int, object>
     */
    public function failedJobs(int $limit = 25): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')->orderByDesc('id')->limit($limit)->get()->all();
    }

    /**
     * @return 'up'|'down'|'unavailable'
     */
    public function aiStatus(): string
    {
        if (! app()->bound(AiScoringClient::class)) {
            return 'unavailable';
        }

        try {
            return app(AiScoringClient::class)->health() ? 'up' : 'down';
        } catch (Throwable) {
            return 'down';
        }
    }

    /**
     * @return array{avg: float|null, p95: int|null, fallback_rate: float|null, count: int}
     */
    public function scoringStats(): array
    {
        $rows = RiskScore::query()->where('created_at', '>=', now()->subDay())
            ->limit(50000)->get(['latency_ms', 'used_fallback']);

        if ($rows->isEmpty()) {
            return ['avg' => null, 'p95' => null, 'fallback_rate' => null, 'count' => 0];
        }

        $latencies = $rows->pluck('latency_ms')->map(fn ($v) => (int) $v)->sort()->values();
        $p95 = $latencies[max(0, (int) ceil(0.95 * $latencies->count()) - 1)];

        return [
            'avg' => round($latencies->avg(), 1),
            'p95' => $p95,
            'fallback_rate' => round($rows->where('used_fallback', true)->count() / $rows->count() * 100, 1),
            'count' => $rows->count(),
        ];
    }

    /**
     * Last error entries of laravel.log (reads only the file tail).
     *
     * @return array<int, string>
     */
    public function recentErrors(int $limit = 50): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return [];
        }

        $read = min($size, 262144);
        fseek($handle, $size - $read);
        $chunk = (string) fread($handle, $read);
        fclose($handle);

        $lines = array_filter(explode("\n", $chunk), fn (string $line) => preg_match('/^\[\d{4}-\d{2}-\d{2}.*\]\s+\S+\.(ERROR|CRITICAL|ALERT|EMERGENCY)/', $line) === 1);

        return array_map(
            fn (string $line) => mb_substr(rtrim($line), 0, 300),
            array_slice(array_values($lines), -$limit),
        );
    }
}
