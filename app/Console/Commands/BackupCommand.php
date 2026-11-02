<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('bank:backup')]
#[Description('Create a database backup in storage/app/private/backups')]
class BackupCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        try {
            $file = $backups->create();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup created: {$file}");

        return self::SUCCESS;
    }
}
