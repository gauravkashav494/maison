<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money columns move from whole rupees to rupees and paise (decimal 12,2). Existing
 * whole-rupee values carry over unchanged (₹1,299 becomes 1299.00). The client-database
 * copy is database/migrations/client/0001_01_01_000006_make_money_columns_decimal.php.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array{nullable?: bool, default?: int}>> */
    private const COLUMNS = [
        'products' => ['price' => [], 'compare_at_price' => ['nullable' => true]],
        'coupons' => ['value' => ['default' => 0], 'min_subtotal' => ['default' => 0]],
        'orders' => ['shipping_cost' => ['default' => 0], 'subtotal' => ['default' => 0], 'discount' => ['default' => 0], 'tax' => ['default' => 0], 'total' => ['default' => 0]],
        'order_items' => ['price' => [], 'total' => []],
    ];

    public function up(): void
    {
        $this->apply(fn (Blueprint $table, string $column) => $table->decimal($column, 12, 2));
    }

    public function down(): void
    {
        $this->apply(fn (Blueprint $table, string $column) => $table->integer($column));
    }

    private function apply(Closure $define): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns, $define) {
                foreach ($columns as $column => $opts) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        continue;
                    }
                    $col = $define($table, $column);
                    if ($opts['nullable'] ?? false) {
                        $col->nullable();
                    }
                    if (array_key_exists('default', $opts)) {
                        $col->default($opts['default']);
                    }
                    $col->change();
                }
            });
        }
    }
};
