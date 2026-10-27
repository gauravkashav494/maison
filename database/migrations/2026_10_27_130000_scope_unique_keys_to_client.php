<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the content keys unique per client instead of per installation.
 *
 * In a shared database two clients would otherwise fight over the same slug: only one of them
 * could have a "shirts" category, a "SALE10" coupon or a menu in the header location. Each
 * unique key therefore gains `client_id`, which is exactly the isolation the rest of the
 * platform applies.
 *
 * Inside a per-client database the composite key behaves the same as the single-column one,
 * because every row there has the one client id.
 */
return new class extends Migration
{
    /** table => [existing index name, columns, new composite columns] */
    private function keys(): array
    {
        return [
            ['products', 'products_slug_unique', ['slug'], ['client_id', 'slug']],
            ['categories', 'categories_slug_unique', ['slug'], ['client_id', 'slug']],
            ['collections', 'collections_slug_unique', ['slug'], ['client_id', 'slug']],
            ['coupons', 'coupons_code_unique', ['code'], ['client_id', 'code']],
            ['pages', 'pages_slug_storefront_template_unique', ['slug', 'storefront_template'], ['client_id', 'storefront_template', 'slug']],
            ['posts', 'posts_slug_template_unique', ['slug', 'template'], ['client_id', 'template', 'slug']],
            ['menus', 'menus_location_unique', ['location'], ['client_id', 'location']],
            ['services', 'services_template_slug_unique', ['template', 'slug'], ['client_id', 'template', 'slug']],
            ['service_areas', 'service_areas_template_slug_unique', ['template', 'slug'], ['client_id', 'template', 'slug']],
            ['projects', 'projects_template_slug_unique', ['template', 'slug'], ['client_id', 'template', 'slug']],
            ['subscribers', 'subscribers_email_unique', ['email'], ['client_id', 'email']],
            ['orders', 'orders_number_unique', ['number'], ['client_id', 'number']],
            ['products', 'products_sku_unique', ['sku'], ['client_id', 'sku']],
            ['service_requests', 'service_requests_reference_unique', ['reference'], ['client_id', 'reference']],
        ];
    }

    public function up(): void
    {
        foreach ($this->keys() as [$table, $name, $columns, $composite]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'client_id')) {
                continue;
            }
            $this->replace($table, $name, $columns, $composite);
        }
    }

    public function down(): void
    {
        foreach ($this->keys() as [$table, $name, $columns, $composite]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->replace($table, $this->nameFor($table, $composite), $composite, $columns, $name);
        }
    }

    private function replace(string $table, string $dropName, array $dropColumns, array $addColumns, ?string $addName = null): void
    {
        $existing = collect(Schema::getIndexes($table));
        $addName ??= $this->nameFor($table, $addColumns);

        if ($existing->contains(fn (array $index) => ($index['name'] ?? null) === $addName)) {
            return; // already converted
        }

        Schema::table($table, function (Blueprint $blueprint) use ($existing, $dropName, $dropColumns) {
            $match = $existing->first(fn (array $index) => ($index['name'] ?? null) === $dropName
                || (! empty($index['unique']) && ($index['columns'] ?? []) === $dropColumns));
            if ($match) {
                $blueprint->dropUnique($match['name']);
            }
        });

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($addColumns, $addName));
    }

    private function nameFor(string $table, array $columns): string
    {
        return $table.'_'.implode('_', $columns).'_unique';
    }
};
