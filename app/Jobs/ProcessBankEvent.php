<?php

namespace App\Jobs;

use App\Contracts\ScoringPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBankEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload  must carry an idempotency_key so retries never duplicate
     */
    public function __construct(public readonly array $payload) {}

    public function handle(ScoringPipeline $pipeline): void
    {
        $pipeline->process($this->payload, false);
    }
}
