<?php

/*
 * Demo shop software's API, as docs/LOCAL-API.md asks a shop's software to provide. Run it with
 * PHP's own server, from the project folder:
 *
 *     php -S 127.0.0.1:8765 tools/demo-shop/server.php
 *
 * then give the agent http://localhost:8765/ecom/v1 and the key demo-key (or DEMO_SHOP_KEY).
 */

require __DIR__.'/shop-lib.php';

const BASE = '/ecom/v1';

function answer(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function logline(string $text): void
{
    file_put_contents('php://stderr', '['.date('H:i:s').'] '.$text.PHP_EOL);
}

/** One page of $items changed at or after ?changed_since (all without it), oldest first. */
function page_of(array $items): never
{
    $since = isset($_GET['changed_since']) ? strtotime($_GET['changed_since']) : null;
    if (isset($_GET['changed_since']) && $since === false) {
        answer(422, ['error' => 'changed_since is not a date']);
    }
    if ($since !== null) {
        $items = array_values(array_filter($items, fn (array $item) => strtotime($item['updated_at']) >= $since));
    }
    usort($items, fn (array $a, array $b) => strcmp($a['updated_at'], $b['updated_at']));

    $perPage = max(1, min(1000, (int) ($_GET['per_page'] ?? 500)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    answer(200, [
        'page' => $page,
        'has_more' => count($items) > $page * $perPage,
        'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
    ]);
}

// ---- Every request ----------------------------------------------------------------------------

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (! str_starts_with($path, BASE.'/')) {
    answer(404, ['error' => 'Not here: the API is under '.BASE]);
}
$path = substr($path, strlen(BASE) + 1);
$method = $_SERVER['REQUEST_METHOD'];
logline("{$method} /{$path}".($_SERVER['QUERY_STRING'] ?? '' ? '?'.$_SERVER['QUERY_STRING'] : ''));

if (! hash_equals(demo_key(), (string) ($_SERVER['HTTP_X_API_KEY'] ?? ''))) {
    answer(401, ['error' => 'Missing or wrong X-Api-Key']);
}
$body = json_decode((string) file_get_contents('php://input'), true) ?? [];

// ---- 10. Health -----------------------------------------------------------------------------

if ($method === 'GET' && $path === 'health') {
    answer(200, ['ok' => true, 'software' => 'Demo Shop', 'version' => '1.0', 'server_time' => demo_now()]);
}

// ---- 2. Products, 3. Stock, 4. Customers, 9. Order status -----------------------------------

if ($method === 'GET' && $path === 'products') {
    $data = demo_transaction(fn (array &$data) => $data);
    page_of(array_map('demo_product_item', array_values($data['products'])));
}

if ($method === 'GET' && $path === 'stock') {
    $data = demo_transaction(fn (array &$data) => $data);
    page_of(array_merge(...array_map('demo_stock_rows', array_values($data['products']))));
}

if ($method === 'GET' && $path === 'customers') {
    $data = demo_transaction(fn (array &$data) => $data);
    page_of(array_values($data['customers']));
}

if ($method === 'GET' && $path === 'order-status') {
    $data = demo_transaction(fn (array &$data) => $data);
    // Only web orders this shop has moved on (dispatched, delivered…) from its own counter.
    $rows = array_values(array_map(fn (array $o) => [
        'number' => $o['number'], 'status' => $o['status'], 'carrier' => $o['carrier'],
        'tracking_number' => $o['tracking_number'], 'note' => $o['note'], 'updated_at' => $o['status_updated_at'],
    ], array_filter($data['orders'], fn (array $o) => $o['status_updated_at'] !== null)));
    page_of($rows);
}

// ---- 5. Web orders --------------------------------------------------------------------------

if ($method === 'POST' && $path === 'orders') {
    $number = (string) ($body['number'] ?? '');
    if ($number === '') {
        answer(422, ['error' => 'The order has no number']);
    }

    $result = demo_transaction(function (array &$data) use ($number, $body) {
        if (isset($data['orders'][$number])) {
            return ['exists', $data['orders'][$number]['local_id']];
        }

        // Every line must name an item this shop has, before anything is saved.
        foreach ($body['lines'] ?? [] as $line) {
            if (demo_stock_record($data, $line['item_external_id'] ?? null, $line['sku'] ?? null) === null) {
                return ['error', 'Unknown item '.($line['item_external_id'] ?? $line['sku'] ?? '?')];
            }
        }

        // The shopper: as linked, else by phone or email, else a new customer.
        $given = $body['customer'] ?? [];
        $customerId = $given['external_id'] ?? null;
        if (! $customerId || ! isset($data['customers'][$customerId])) {
            $customerId = null;
            foreach ($data['customers'] as $id => $c) {
                if (($given['phone'] ?? null) && $c['phone'] === $given['phone'] || ($given['email'] ?? null) && $c['email'] === $given['email']) {
                    $customerId = $id;
                    break;
                }
            }
            if ($customerId === null) {
                $customerId = demo_next_id($data, 'customer', 'PARTY-');
                $data['customers'][$customerId] = ['external_id' => $customerId, 'name' => $given['name'] ?? 'Web customer',
                    'phone' => $given['phone'] ?? null, 'email' => $given['email'] ?? null, 'gstin' => null,
                    'city' => $body['shipping_address']['city'] ?? null, 'is_active' => true, 'updated_at' => demo_now()];
            }
        }

        // Saved and taken off stock together, so the same goods are never sold twice.
        foreach ($body['lines'] ?? [] as $line) {
            $record = &demo_stock_record($data, $line['item_external_id'] ?? null, $line['sku'] ?? null);
            demo_move_stock($record, -(int) $line['qty'], null);
            unset($record);
        }
        $localId = demo_next_id($data, 'sale', 'SALE-');
        $data['orders'][$number] = [
            'local_id' => $localId, 'number' => $number, 'customer' => $customerId, 'status' => $body['status'] ?? 'confirmed',
            'lines' => $body['lines'] ?? [], 'total' => $body['total'] ?? null, 'invoice_number' => null,
            'carrier' => null, 'tracking_number' => null, 'note' => null, 'status_updated_at' => null, 'restocked' => false,
            'received_at' => demo_now(),
        ];

        return ['created', $localId];
    });

    if ($result[0] === 'error') {
        answer(422, ['error' => $result[1]]);
    }
    logline("  order {$number}: {$result[0]} as {$result[1]}");
    answer(200, ['result' => $result[0], 'local_id' => $result[1]]);
}

// ---- 6. Changes to a web order --------------------------------------------------------------

if ($method === 'POST' && preg_match('#^orders/(.+)/update$#', $path, $m)) {
    $number = rawurldecode($m[1]);
    $result = demo_transaction(function (array &$data) use ($number, $body) {
        if (! isset($data['orders'][$number])) {
            return null;
        }
        $order = &$data['orders'][$number];
        $before = json_encode($order);

        // Cancelled: the stock goes back once only, however often the update arrives.
        if (($body['status'] ?? null) === 'cancelled' && ! $order['restocked']) {
            foreach ($order['lines'] as $line) {
                $record = &demo_stock_record($data, $line['item_external_id'] ?? null, $line['sku'] ?? null);
                if ($record !== null) {
                    demo_move_stock($record, (int) $line['qty'], null);
                }
                unset($record);
            }
            $order['restocked'] = true;
        }
        foreach (['status', 'invoice_number', 'payment_status'] as $field) {
            if (isset($body[$field])) {
                $order[$field] = $body[$field];
            }
        }

        return json_encode($order) === $before ? 'unchanged' : 'updated';
    });

    if ($result === null) {
        answer(404, ['error' => "No order {$number}"]);
    }
    logline("  order {$number}: {$result}");
    answer(200, ['result' => $result]);
}

// ---- 7. Products added on the online store --------------------------------------------------

if ($method === 'POST' && $path === 'products') {
    $result = demo_transaction(function (array &$data) use ($body) {
        $found = null;
        if (! empty($body['external_id']) && isset($data['products'][$body['external_id']])) {
            $found = $body['external_id'];
        }
        foreach ($found ? [] : $data['products'] as $id => $p) {
            if (! empty($body['sku']) && $p['sku'] === $body['sku']) {
                $found = $id;
            }
        }

        $variant = fn (array $v) => ['options' => $v['options'] ?? [], 'sku' => $v['sku'] ?? null,
            'price_incl_tax' => $v['price_incl_tax'] ?? null, 'price_excl_tax' => $v['price_excl_tax'] ?? null,
            'hsn_code' => $v['hsn_code'] ?? null, 'is_active' => $v['is_active'] ?? true,
            'on_hand' => 0, 'stock_reason' => null, 'stock_note' => null, 'stock_updated_at' => demo_now()];

        if ($found === null) {
            // A new item, with its categories and variants; stock starts at 0.
            $id = demo_next_id($data, 'product', 'ITM-');
            $data['products'][$id] = ['external_id' => $id, 'unit' => 'pcs', 'updated_at' => demo_now(),
                'on_hand' => 0, 'stock_reason' => null, 'stock_note' => null, 'stock_updated_at' => demo_now(),
                'variants' => array_map($variant, $body['variants'] ?? [])]
                + array_intersect_key($body, array_flip(['name', 'brand', 'category', 'category_2', 'category_3', 'category_4',
                    'is_active', 'mrp', 'tax_rate', 'hsn_code', 'price_incl_tax', 'price_excl_tax', 'sku']));

            return ['created', $id];
        }

        // One this shop has: add new variants, update options; its name, prices and stock stay.
        $product = &$data['products'][$found];
        foreach ($body['variants'] ?? [] as $incoming) {
            $known = false;
            foreach ($product['variants'] as $i => $v) {
                if ($v['sku'] === ($incoming['sku'] ?? null)) {
                    $product['variants'][$i]['options'] = $incoming['options'] ?? $v['options'];
                    $known = true;
                }
            }
            if (! $known) {
                $product['variants'][] = $variant($incoming);
            }
        }
        $product['updated_at'] = demo_now();

        return ['updated', $found];
    });

    logline("  product {$body['name']}: {$result[0]} as {$result[1]}");
    answer(200, ['result' => $result[0], 'local_id' => $result[1]]);
}

// ---- 8. Customers who signed up online ------------------------------------------------------

if ($method === 'POST' && $path === 'customers') {
    $result = demo_transaction(function (array &$data) use ($body) {
        $found = null;
        foreach ($data['customers'] as $id => $c) {
            $match = (! empty($body['external_id']) && $id === $body['external_id'])
                || (! empty($body['phone']) && $c['phone'] === $body['phone'])
                || (! empty($body['email']) && $c['email'] === $body['email']);
            if ($match) {
                $found = $id;
                break;
            }
        }

        $id = $found ?? demo_next_id($data, 'customer', 'PARTY-');
        $data['customers'][$id] = [
            'external_id' => $id, 'name' => $body['name'] ?? ($data['customers'][$id]['name'] ?? 'Web customer'),
            'phone' => $body['phone'] ?? null, 'email' => $body['email'] ?? null,
            'gstin' => $data['customers'][$id]['gstin'] ?? null, 'city' => $data['customers'][$id]['city'] ?? null,
            'is_active' => $body['is_active'] ?? true, 'updated_at' => demo_now(),
        ];

        return [$found ? 'updated' : 'created', $id];
    });

    logline("  customer {$body['name']}: {$result[0]} as {$result[1]}");
    answer(200, ['result' => $result[0], 'local_id' => $result[1]]);
}

answer(404, ['error' => "No such endpoint: {$method} /{$path}"]);
