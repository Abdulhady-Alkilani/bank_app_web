<?php

namespace App\Contracts;

use App\Data\ScoringResult;

interface ScoringPipeline
{
    /**
     * Ingest and score an event/transaction payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  bool  $sync  true: score inline and return the result; false: may defer heavy work to the queue
     */
    public function process(array $payload, bool $sync = true): ScoringResult;
}
