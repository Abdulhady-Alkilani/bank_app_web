<?php

namespace App\Jobs;

use App\Services\Bank\BankWebhookNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBankWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly string $command, public readonly array $data = []) {}

    public function handle(BankWebhookNotifier $notifier): void
    {
        if ($notifier->notify($this->command, $this->data)) {
            return;
        }

        // Retry with back-off on a real queue; the inline (sync) driver just logs the failure.
        if ($this->job && $this->job->getConnectionName() !== 'sync') {
            $this->release(15 * $this->attempts());
        }
    }
}
