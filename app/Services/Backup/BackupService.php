<?php

namespace App\Services\Backup;

use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    public const DIRECTORY = 'backups';

    private const SKIP_TABLES = ['cache', 'cache_locks', 'sessions', 'sqlite_sequence'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create a backup and return its file name (relative to the backups directory).
     */
    public function create(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);
        $stamp = now()->format('Ymd_His');

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $name = null;

        if ($driver === 'sqlite') {
            $database = (string) $connection->getDatabaseName();
            if ($database !== '' && $database !== ':memory:' && is_file($database)) {
                $name = $stamp.'.sqlite';
                copy($database, $disk->path(self::DIRECTORY.'/'.$name));
            }
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $name = $this->mysqldump($stamp);
        }

        $name ??= $this->jsonZip($stamp);

        $this->audit->log('backup.created', null, [], [
            'file' => $name,
            'size' => $disk->size(self::DIRECTORY.'/'.$name),
        ]);

        return $name;
    }

    /**
     * @return array<int, array{name: string, size: int, modified: int}>
     */
    public function list(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::DIRECTORY)) {
            return [];
        }

        return collect($disk->files(self::DIRECTORY))->map(fn (string $path) => [
            'name' => basename($path),
            'size' => (int) $disk->size($path),
            'modified' => (int) $disk->lastModified($path),
        ])->sortByDesc('modified')->values()->all();
    }

    public function path(string $file): string
    {
        $file = basename($file);
        $disk = Storage::disk('local');

        if (! $disk->exists(self::DIRECTORY.'/'.$file)) {
            throw new RuntimeException(__('admin.backups.not_found'));
        }

        return $disk->path(self::DIRECTORY.'/'.$file);
    }

    /**
     * Restore a JSON-zip (or sqlite) backup.
     */
    public function restore(string $file): void
    {
        $path = $this->path($file);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'sqlite') {
            $database = (string) DB::connection()->getDatabaseName();
            if (DB::connection()->getDriverName() !== 'sqlite' || ! is_file($database)) {
                throw new RuntimeException(__('admin.backups.restore_unsupported'));
            }
            copy($path, $database);
        } elseif ($extension === 'zip') {
            $this->restoreJson($path);
        } else {
            throw new RuntimeException(__('admin.backups.restore_unsupported'));
        }

        $this->audit->log('backup.restored', null, [], ['file' => basename($path)]);
    }

    private function mysqldump(string $stamp): ?string
    {
        $binary = (new ExecutableFinder)->find('mysqldump');
        if (! $binary) {
            return null;
        }

        $config = config('database.connections.'.config('database.default'));
        $name = $stamp.'.sql';
        $target = Storage::disk('local')->path(self::DIRECTORY.'/'.$name);

        $process = new Process([
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--single-transaction', '--routines', '--no-tablespaces',
            '--result-file='.$target,
            (string) ($config['database'] ?? ''),
        ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')], null, 300);

        $process->run();

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            @unlink($target);

            return null;
        }

        return $name;
    }

    private function jsonZip(string $stamp): string
    {
        $name = $stamp.'.zip';
        $target = Storage::disk('local')->path(self::DIRECTORY.'/'.$name);

        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }

        $tables = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $table) => in_array($table, self::SKIP_TABLES, true))->values()->all();

        $temp = [];
        foreach ($tables as $table) {
            $file = tempnam(sys_get_temp_dir(), 'bkp');
            $temp[] = $file;
            $handle = fopen($file, 'wb');
            fwrite($handle, '[');
            $first = true;
            foreach (DB::table($table)->cursor() as $row) {
                fwrite($handle, ($first ? '' : ',').json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                $first = false;
            }
            fwrite($handle, ']');
            fclose($handle);
            $zip->addFile($file, 'tables/'.$table.'.json');
        }

        $zip->addFromString('manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'driver' => DB::connection()->getDriverName(),
            'tables' => $tables,
        ], JSON_PRETTY_PRINT));
        $zip->close();

        foreach ($temp as $file) {
            @unlink($file);
        }

        return $name;
    }

    private function restoreJson(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('admin.backups.invalid'));
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ! isset($manifest['tables'])) {
            $zip->close();
            throw new RuntimeException(__('admin.backups.invalid'));
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($manifest['tables'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $rows = json_decode((string) $zip->getFromName('tables/'.$table.'.json'), true) ?? [];
                DB::table($table)->delete();
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
            $zip->close();
        }
    }
}
