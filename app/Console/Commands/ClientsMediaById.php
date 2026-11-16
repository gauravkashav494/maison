<?php

namespace App\Console\Commands;

use App\Clients\ClientDatabase;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves each client's uploads from clients/<subdomain>/ to clients/<id>/ on the public disk and
 * rewrites every stored path to match: in the client's own database (products.images and
 * variants, categories, pages, settings blocks, …) and in the platform database (settings).
 *
 * The paths are only rewritten once the files have moved, so the two never disagree. Run it as
 * the user that owns the uploads (the web server, e.g. `sudo -u www-data php artisan …`).
 * Safe to run again: a client already moved has nothing left to do.
 */
class ClientsMediaById extends Command
{
    protected $signature = 'clients:media-by-id {subdomain? : Only this client} {--dry-run : Report what would change without changing it}';

    protected $description = 'Move client uploads to folders named by client id and update the stored paths';

    /** Tables that never hold an upload path. */
    private const SKIP_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'platform_activities'];

    public function handle(ClientDatabase $databases): int
    {
        $disk = Storage::disk('public');
        $dry = (bool) $this->option('dry-run');
        $failed = false;

        $clients = Client::withTrashed()->get()
            ->when($this->argument('subdomain'), fn ($all) => $all->where('subdomain', $this->argument('subdomain')));

        foreach ($clients as $client) {
            $from = $client->legacyMediaDirectory();
            $to = $client->mediaDirectory();
            if ($from === $to || blank($client->subdomain)) {
                continue;
            }

            // 1. Files first.
            $files = $disk->exists($from) ? $disk->allFiles($from) : [];
            if ($files && ! $dry && ! $this->moveFolder($disk->path($from), $disk->path($to))) {
                $this->error("{$client->name}: could not move {$from} to {$to} (file permissions?). Paths left unchanged.");
                $failed = true;

                continue;
            }

            // 2. Then every stored reference, in both spellings ("a/b" and JSON's "a\/b").
            // With a database per client the platform database only holds settings and clients;
            // with shared tables (CLIENT_ISOLATION=shared) everything is there. The folder name is
            // unique to the client either way, so replacing it touches no one else's rows.
            $isolated = $databases->isolated();
            $changed = $this->rewrite(DB::connection()->getName(), $from, $to, $dry, $isolated ? ['settings', 'clients'] : null);
            try {
                $changed += $isolated ? $databases->forClient($client, fn () => $databases->connection()
                    ? $this->rewrite($databases->connection(), $from, $to, $dry)
                    : 0) : 0;
            } catch (Throwable $e) {
                $this->warn("{$client->name}: its database could not be read ({$e->getMessage()}).");
            }

            $this->line(sprintf('%s: %d file(s) %s %s → %s, %d stored path(s) %s.',
                $client->name, count($files), $dry ? 'would move' : 'moved', $from, $to, $changed, $dry ? 'would change' : 'updated'));
        }

        if (! $dry) {
            Cache::forget('settings.all'); // settings are cached with the old paths
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Moves the folder, merging into the target when it already exists. */
    private function moveFolder(string $from, string $to): bool
    {
        try {
            if (! File::isDirectory($to)) {
                File::ensureDirectoryExists(dirname($to));

                return File::moveDirectory($from, $to);
            }
            foreach (File::allFiles($from) as $file) {
                $target = $to.'/'.$file->getRelativePathname();
                File::ensureDirectoryExists(dirname($target));
                if (! File::move($file->getPathname(), $target)) {
                    return false;
                }
            }
            File::deleteDirectory($from);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Replaces "<from>/" with "<to>/" in every text column of every table on the connection
     * (or only the listed tables). Returns the number of rows changed.
     */
    private function rewrite(string $connection, string $from, string $to, bool $dry, ?array $only = null): int
    {
        $schema = Schema::connection($connection);
        $db = DB::connection($connection);
        $subdomain = substr($from, strlen('clients/'));
        $pairs = [[$from.'/', $to.'/'], [str_replace('/', '\\/', $from.'/'), str_replace('/', '\\/', $to.'/')]];
        $rows = 0;

        // On MySQL the listing covers every database on the server; keep this connection's tables.
        foreach (array_unique($schema->getTableListing(schemaQualified: false)) as $table) {
            if (in_array($table, self::SKIP_TABLES, true) || ($only !== null && ! in_array($table, $only, true)) || ! $schema->hasTable($table)) {
                continue;
            }
            $all = collect($schema->getColumns($table));
            // Rows are written back by their primary key (id, or e.g. settings.key).
            $primary = collect($schema->getIndexes($table))->firstWhere('primary', true)['columns'] ?? [];
            $key = count($primary) === 1 ? $primary[0] : ($all->pluck('name')->contains('id') ? 'id' : null);
            if ($key === null) {
                continue;
            }
            $columns = $all
                ->filter(fn (array $c) => preg_match('/char|text|json/i', $c['type_name'] ?? $c['type'] ?? ''))
                ->pluck('name');

            foreach ($columns as $column) {
                // The subdomain appears in both spellings ("a/b" and "a\/b"), so it narrows the rows;
                // the exact replacement is done here, and only rows it changes are written back.
                $matches = $db->table($table)->where($column, 'like', '%'.$subdomain.'%')->get([$key, $column]);
                foreach ($matches as $row) {
                    $old = (string) $row->{$column};
                    $new = str_replace(array_column($pairs, 0), array_column($pairs, 1), $old);
                    if ($new === $old) {
                        continue;
                    }
                    $rows++;
                    if (! $dry) {
                        $db->table($table)->where($key, $row->{$key})->update([$column => $new]);
                    }
                }
            }
        }

        return $rows;
    }
}
