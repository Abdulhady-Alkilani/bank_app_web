<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('bank:backup:restore {file : Backup file name inside storage/app/private/backups} {--force : Skip the confirmation prompt}')]
#[Description('Restore a JSON-zip (or sqlite) backup, replacing current data')]
class BackupRestoreCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        if (! $this->option('force') && ! $this->confirm('This replaces ALL current data. Continue?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        try {
            $backups->restore((string) $this->argument('file'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup restored.');

        return self::SUCCESS;
    }
}
