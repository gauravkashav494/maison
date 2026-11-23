<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves stored times from UTC clock time to India time (UTC+5:30), for the switch of
 * config('app.timezone') to Asia/Kolkata. Run once by hand from tinker, on the shared database
 * and on each client database (pass its connection name).
 *
 * Every date-time column is shifted except the ones staff typed in on a date picker: those were
 * already entered as India time. Order timelines (orders.status_history) keep each time with its
 * offset, so they are rewritten to +05:30 too. Date-only columns have no time and are left alone.
 */
class IndiaTimeShift
{
    public const MINUTES = 330;

    /** Times typed in by staff, already India time. */
    private const ENTERED = [
        'coupons' => ['starts_at', 'ends_at'],
        'posts' => ['published_at'],
    ];

    /** @param  int  $direction  1 moves UTC to India time, -1 moves it back */
    public static function run(int $direction, ?string $connection = null): void
    {
        $minutes = self::MINUTES * $direction;
        $db = DB::connection($connection);
        $schema = Schema::connection($connection);
        $driver = $db->getDriverName();

        foreach ($schema->getTables() as $table) {
            $name = $table['name'];
            $columns = array_filter(
                $schema->getColumns($name),
                fn (array $c) => preg_match('/^(datetime|timestamp)/i', (string) $c['type_name'])
                    && ! in_array($c['name'], self::ENTERED[$name] ?? [], true),
            );

            foreach ($columns as $column) {
                $col = $column['name'];
                $expression = $driver === 'sqlite'
                    ? "datetime(\"{$col}\", '".sprintf('%+d', $minutes)." minutes')"
                    : "DATE_ADD(`{$col}`, INTERVAL {$minutes} MINUTE)";

                $db->table($name)->whereNotNull($col)->update([$col => DB::raw($expression)]);
            }
        }

        if ($schema->hasTable('orders') && $schema->hasColumn('orders', 'status_history')) {
            $zone = $direction > 0 ? 'Asia/Kolkata' : 'UTC';
            $db->table('orders')->whereNotNull('status_history')->orderBy('id')->chunkById(500, function ($orders) use ($zone, $db) {
                foreach ($orders as $order) {
                    $history = json_decode((string) $order->status_history, true);
                    if (! is_array($history)) {
                        continue;
                    }
                    foreach ($history as &$entry) {
                        if (is_array($entry) && filled($entry['at'] ?? null)) {
                            $entry['at'] = Carbon::parse($entry['at'])->setTimezone($zone)->toIso8601String();
                        }
                    }
                    $db->table('orders')->where('id', $order->id)->update(['status_history' => json_encode($history)]);
                }
            });
        }
    }
}
