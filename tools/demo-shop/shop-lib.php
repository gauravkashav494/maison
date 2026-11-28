<?php

/*
 * Demo shop software: shared by server.php (the API in docs/LOCAL-API.md) and shop.php (the
 * shop counter). A stand-in for a shop's real billing software, for testing the sync agent end
 * to end and as a working example for the software's developer. Not part of the Laravel app.
 *
 * Everything lives in data.json next to this file, created from the sample data on first use.
 */

date_default_timezone_set('Asia/Kolkata');

const DEMO_DATA = __DIR__.'/data.json';

function demo_key(): string
{
    return getenv('DEMO_SHOP_KEY') ?: 'demo-key';
}

/** The shop PC's clock, as every updated_at is stamped. */
function demo_now(): string
{
    return date('c');
}

/**
 * Runs $work on the data with the file locked, and saves what it leaves, so the agent's calls
 * and the shop counter never overwrite each other.
 */
function demo_transaction(callable $work): mixed
{
    $handle = fopen(DEMO_DATA, 'c+');
    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $data = $raw ? json_decode($raw, true) : demo_seed();

    $result = $work($data);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    flock($handle, LOCK_UN);
    fclose($handle);

    return $result;
}

function demo_next_id(array &$data, string $kind, string $prefix): string
{
    $data['next'][$kind] = ($data['next'][$kind] ?? 1000) + 1;

    return $prefix.$data['next'][$kind];
}

/** Sample items: one sold by SKU, one with no SKU at all, one sold in variants. */
function demo_seed(): array
{
    $at = demo_now();
    $stock = fn (int $on, ?string $reason = 'count', ?string $note = 'Opening stock') => ['on_hand' => $on, 'stock_reason' => $reason, 'stock_note' => $note, 'stock_updated_at' => $at];

    return [
        'products' => [
            'ITM-1001' => [
                'external_id' => 'ITM-1001', 'name' => 'Lux Soap 100 g', 'brand' => 'Lux',
                'category' => 'Bath & Body', 'category_2' => 'Soaps', 'category_3' => null, 'category_4' => null,
                'is_active' => true, 'mrp' => 50.00, 'tax_rate' => 18, 'hsn_code' => '3401',
                'price_incl_tax' => 45.00, 'price_excl_tax' => 38.14, 'sku' => 'SOAP-LUX-100', 'unit' => 'pcs',
                'variants' => [], 'updated_at' => $at,
            ] + $stock(120),
            'ITM-1002' => [
                'external_id' => 'ITM-1002', 'name' => 'Loose Basmati Rice 1 kg', 'brand' => null,
                'category' => 'Grocery', 'category_2' => null, 'category_3' => 'Sale', 'category_4' => null,
                'is_active' => true, 'mrp' => null, 'tax_rate' => 5, 'hsn_code' => '1006',
                'price_incl_tax' => 110.00, 'price_excl_tax' => null, 'sku' => null, 'unit' => 'kg',
                'variants' => [], 'updated_at' => $at,
            ] + $stock(40),
            'ITM-2040' => [
                'external_id' => 'ITM-2040', 'name' => 'Cotton T-Shirt', 'brand' => 'Acme',
                'category' => 'Men', 'category_2' => 'T-Shirts', 'category_3' => null, 'category_4' => null,
                'is_active' => true, 'mrp' => 699.00, 'tax_rate' => 5, 'hsn_code' => '6109',
                'price_incl_tax' => 499.00, 'price_excl_tax' => 475.24, 'sku' => 'TEE', 'unit' => 'pcs',
                'variants' => [
                    ['options' => ['Colour' => 'Black', 'Size' => 'M'], 'sku' => 'TEE-BLK-M', 'price_incl_tax' => 499.00, 'is_active' => true] + $stock(8),
                    ['options' => ['Colour' => 'Black', 'Size' => 'L'], 'sku' => 'TEE-BLK-L', 'price_incl_tax' => 549.00, 'is_active' => true] + $stock(10),
                ],
                'updated_at' => $at, 'on_hand' => 0, 'stock_reason' => null, 'stock_note' => null, 'stock_updated_at' => $at,
            ],
        ],
        'customers' => [
            'PARTY-311' => ['external_id' => 'PARTY-311', 'name' => 'Priya Sharma', 'phone' => '9876543210', 'email' => 'priya@example.com', 'gstin' => null, 'city' => 'Pune', 'is_active' => true, 'updated_at' => $at],
            'PARTY-312' => ['external_id' => 'PARTY-312', 'name' => 'Rahul Verma', 'phone' => '9811122233', 'email' => null, 'gstin' => null, 'city' => 'Delhi', 'is_active' => true, 'updated_at' => $at],
        ],
        'orders' => [],
        'next' => ['product' => 4120, 'customer' => 500, 'sale' => 55000],
    ];
}

/**
 * The stock record a line or a count names: the item, or its variant by SKU for an item sold
 * in variants. Returned by reference, so callers change it in place.
 */
function &demo_stock_record(array &$data, ?string $itemId, ?string $sku): ?array
{
    $none = null;
    if ($itemId === null || ! isset($data['products'][$itemId])) {
        // Found by SKU alone: an item's own, or a variant's.
        foreach ($data['products'] as $id => $product) {
            if ($sku !== null && $product['sku'] === $sku && $product['variants'] === []) {
                return $data['products'][$id];
            }
            foreach ($product['variants'] as $i => $variant) {
                if ($sku !== null && $variant['sku'] === $sku) {
                    return $data['products'][$id]['variants'][$i];
                }
            }
        }

        return $none;
    }

    $product = &$data['products'][$itemId];
    if ($product['variants'] === []) {
        return $product;
    }
    foreach ($product['variants'] as $i => $variant) {
        if ($sku !== null && $variant['sku'] === $sku) {
            return $product['variants'][$i];
        }
    }

    return $none;
}

/** An item as GET /products lists it. */
function demo_product_item(array $p): array
{
    $variants = array_map(fn (array $v) => [
        'options' => $v['options'], 'sku' => $v['sku'], 'price_incl_tax' => $v['price_incl_tax'] ?? null,
        'price_excl_tax' => $v['price_excl_tax'] ?? null, 'hsn_code' => $v['hsn_code'] ?? null,
        'is_active' => $v['is_active'], 'on_hand' => $v['on_hand'],
    ], $p['variants']);

    return array_intersect_key($p, array_flip([
        'external_id', 'name', 'brand', 'category', 'category_2', 'category_3', 'category_4', 'is_active',
        'mrp', 'tax_rate', 'hsn_code', 'price_incl_tax', 'price_excl_tax', 'sku', 'unit', 'updated_at',
    ])) + ['on_hand' => $p['variants'] === [] ? $p['on_hand'] : null, 'variants' => $variants];
}

/** An item's GET /stock rows: its own, or one per variant for an item sold in variants. */
function demo_stock_rows(array $p): array
{
    $rows = [];
    foreach ($p['variants'] === [] ? [$p] : $p['variants'] as $record) {
        $rows[] = [
            'item_external_id' => $p['external_id'],
            'sku' => $p['variants'] === [] ? $p['sku'] : $record['sku'],
            'on_hand' => $record['on_hand'], 'reason' => $record['stock_reason'], 'note' => $record['stock_note'],
            'updated_at' => $record['stock_updated_at'],
        ];
    }

    return $rows;
}

/** Changes a stock record by $change, with why (null for a web order: the store recorded it). */
function demo_move_stock(array &$record, int $change, ?string $reason, ?string $note = null): void
{
    $record['on_hand'] = max(0, (int) $record['on_hand'] + $change);
    $record['stock_reason'] = $reason;
    $record['stock_note'] = $note;
    $record['stock_updated_at'] = demo_now();
}
