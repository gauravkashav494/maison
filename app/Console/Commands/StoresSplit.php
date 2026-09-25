<?php

namespace App\Console\Commands;

use App\Models\Storefront;
use App\Stores\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies each store's rows out of the main database into the store's own database.
 *
 *   php artisan stores:split               # every store
 *   php artisan stores:split grocery       # one store
 *   php artisan stores:split --pretend     # show what would move
 *
 * Rows shared by every template (template = null) are copied into each store so nothing
 * disappears. The main database is never modified — once the stores are verified you can
 * clean it up yourself with `stores:split --prune` (kept as a separate, explicit step).
 */
class StoresSplit extends Command
{
    use \App\Stores\SuspendsForeignKeys;

    protected $signature = 'stores:split
        {slug? : Only this store}
        {--pretend : Report the row counts without copying}
        {--keep : Do not empty the store database tables first}
        {--prune : After copying, delete the store rows from the main database (irreversible)}';

    protected $description = 'Copy store content from the main database into the per-store databases';

    /** Tables that carry the owning template, and the ones that hang off them. */
    private const OWNED_BY = [
        'order_items' => ['orders', 'order_id'],
        'menu_items' => ['menus', 'menu_id'],
        'collection_product' => ['collections', 'collection_id'],
    ];

    public function handle(TenantManager $tenants): int
    {
        if (! $tenants->isolated()) {
            $this->error('Store isolation is off. Set STORE_ISOLATION=database (or schema) in .env first.');

            return self::FAILURE;
        }

        $main = DB::connection(config('stores.connection') ?: config('database.default'));
        $stores = $tenants->stores()->when($this->argument('slug'), fn ($c) => $c->where('slug', $this->argument('slug')));

        foreach ($stores as $store) {
            $this->newLine();
            $this->info($store->name.'  →  '.$tenants->databaseNameFor($store));
            $target = DB::connection($tenants->register($store));
            $moved = 0;

            $this->withoutForeignKeys($target, function () use ($main, $target, $store, &$moved) {
            foreach (config('stores.tables', []) as $table) {
                if (! Schema::connection($main->getName())->hasTable($table) || ! Schema::connection($target->getName())->hasTable($table)) {
                    continue;
                }
                $rows = $this->rowsFor($main, $table, $store);
                $moved += $rows->count();

                if ($this->option('pretend')) {
                    $rows->isNotEmpty() && $this->line(sprintf('  %-22s %s rows', $table, number_format($rows->count())));

                    continue;
                }

                if (! $this->option('keep')) {
                    $target->table($table)->delete();
                }
                foreach ($rows->chunk(400) as $chunk) {
                    $target->table($table)->insert($chunk->map(fn ($row) => (array) $row)->all());
                }
                $rows->isNotEmpty() && $this->line(sprintf('  %-22s %s rows', $table, number_format($rows->count())));
            }

            });

            $this->line('  '.number_format($moved).' rows in total');
        }

        if ($this->option('prune') && ! $this->option('pretend')) {
            $this->pruneMain($main, $stores);
        }

        $this->newLine();
        $this->info($this->option('pretend') ? 'Nothing was written (--pretend).' : 'Store data copied. Check each store, then re-run with --prune to clear the main database.');

        return self::SUCCESS;
    }

    /** Rows of a table that belong to this store: its own template plus the rows shared by all. */
    private function rowsFor($main, string $table, Storefront $store)
    {
        if (isset(self::OWNED_BY[$table])) {
            [$parent, $foreignKey] = self::OWNED_BY[$table];
            $parentIds = $this->rowsFor($main, $parent, $store)->pluck('id');

            return $main->table($table)->whereIn($foreignKey, $parentIds)->get();
        }

        $column = $table === 'pages' ? 'storefront_template' : 'template';
        if (! Schema::connection($main->getName())->hasColumn($table, $column)) {
            return collect();
        }

        return $main->table($table)
            ->where($column, $store->template)
            ->orWhereNull($column)   // rows shared by every template are copied into each store
            ->get();
    }

    private function pruneMain($main, $stores): void
    {
        if (! $this->confirm('Delete the copied store rows from the main database? This cannot be undone.', false)) {
            return;
        }
        $templates = $stores->pluck('template')->all();
        foreach (array_reverse(config('stores.tables', [])) as $table) {
            if (isset(self::OWNED_BY[$table]) || ! Schema::connection($main->getName())->hasTable($table)) {
                continue;
            }
            $column = $table === 'pages' ? 'storefront_template' : 'template';
            if (! Schema::connection($main->getName())->hasColumn($table, $column)) {
                continue;
            }
            $deleted = $main->table($table)->whereIn($column, $templates)->delete();
            $deleted && $this->line(sprintf('  pruned %-20s %s rows', $table, number_format($deleted)));
        }
    }
}
