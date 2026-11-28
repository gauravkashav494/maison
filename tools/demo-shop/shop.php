<?php

/*
 * Demo shop counter: does what staff do in a shop's billing software, so the changes reach the
 * online store on the agent's next cycle. From the project folder:
 *
 *     php tools/demo-shop/shop.php list
 *     php tools/demo-shop/shop.php sell ITM-1001 2
 *     php tools/demo-shop/shop.php receive ITM-2040 TEE-BLK-M 5 GRN-2231
 *     php tools/demo-shop/shop.php count ITM-1002 35
 *     php tools/demo-shop/shop.php price ITM-1001 48
 *     php tools/demo-shop/shop.php add "Steel Bottle 1 L" 349 BOTTLE-1L 20
 *     php tools/demo-shop/shop.php dispatch EFS-261127-K7QM BlueDart BD123456789
 *     php tools/demo-shop/shop.php deliver EFS-261127-K7QM
 *     php tools/demo-shop/shop.php reset
 */

require __DIR__.'/shop-lib.php';

const NEXT_SYNC = "The online store gets this on the agent's next sync, within 5 minutes.";

$args = array_slice($argv, 1);
$command = array_shift($args) ?? 'help';

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

/** "ITM-2040 TEE-BLK-M 5 [note]" or "ITM-1001 5 [note]": the item, its variant SKU if any, the quantity. */
function item_args(array $args): array
{
    $item = $args[0] ?? fail('Which item? e.g. ITM-1001');
    $sku = isset($args[1]) && ! is_numeric($args[1]) ? $args[1] : null;
    $rest = array_slice($args, $sku ? 2 : 1);
    $qty = isset($rest[0]) && is_numeric($rest[0]) ? (int) $rest[0] : fail('How many?');

    return [$item, $sku, $qty, $rest[1] ?? null];
}

/** Moves or sets the stock of an item or variant, with the reason the online store shows. */
function stock(array $args, string $reason, bool $set = false): void
{
    [$item, $sku, $qty, $note] = item_args($args);
    [$after, $product] = demo_transaction(function (array &$data) use ($item, $sku, $qty, $note, $reason, $set) {
        $record = &demo_stock_record($data, $item, $sku);
        if ($record === null) {
            $variants = array_column($data['products'][$item]['variants'] ?? [], 'sku');
            fail(match (true) {
                ! isset($data['products'][$item]) => "No item {$item}. Run list to see them.",
                $sku === null => "{$item} is sold in variants: give one, e.g. {$item} {$variants[0]} 2",
                default => "{$item} has no variant {$sku}. Its variants: ".implode(', ', $variants),
            });
        }
        demo_move_stock($record, $set ? $qty - (int) $record['on_hand'] : ($reason === 'sale' ? -$qty : $qty), $reason, $note);

        return [$record['on_hand'], $data['products'][$item]];
    });
    echo "{$item}".($sku ? " {$sku}" : '')." now {$after} in stock ({$reason}).".PHP_EOL;
    echo NEXT_SYNC.PHP_EOL;
}

/** Moves a web order on from the counter, for GET /order-status. */
function order_status(array $args, string $status): void
{
    $number = $args[0] ?? fail('Which order number?');
    $order = demo_transaction(function (array &$data) use ($number, $status, $args) {
        if (! isset($data['orders'][$number])) {
            fail("No web order {$number}. Run list to see them.");
        }
        $order = &$data['orders'][$number];
        $order['status'] = $status;
        $order['carrier'] = $args[1] ?? $order['carrier'];
        $order['tracking_number'] = $args[2] ?? $order['tracking_number'];
        $order['status_updated_at'] = demo_now();

        return $order;
    });
    echo "{$number} is now {$status}.".PHP_EOL;
    echo NEXT_SYNC.PHP_EOL;
}

switch ($command) {
    case 'list':
        $data = demo_transaction(fn (array &$data) => $data);
        echo 'Items'.PHP_EOL;
        foreach ($data['products'] as $p) {
            $price = $p['price_incl_tax'] ?? $p['price_excl_tax'];
            if ($p['variants'] === []) {
                printf("  %-9s %-28s %-14s Rs %-8s stock %d\n", $p['external_id'], $p['name'], $p['sku'] ?? '(no SKU)', $price, $p['on_hand']);
            } else {
                printf("  %-9s %-28s %-14s Rs %s\n", $p['external_id'], $p['name'], $p['sku'] ?? '(no SKU)', $price);
                foreach ($p['variants'] as $v) {
                    printf("              %-26s %-14s %-11s stock %d\n", implode(' / ', $v['options']), $v['sku'], '', $v['on_hand']);
                }
            }
        }
        echo PHP_EOL.'Customers'.PHP_EOL;
        foreach ($data['customers'] as $c) {
            printf("  %-10s %-20s %s\n", $c['external_id'], $c['name'], $c['phone'] ?? $c['email'] ?? '');
        }
        echo PHP_EOL.'Web orders'.PHP_EOL;
        foreach ($data['orders'] ?: [] as $o) {
            printf("  %-18s %-11s %-12s %-10s Rs %s\n", $o['number'], $o['local_id'], $o['status'], $o['customer'], $o['total']);
        }
        if ($data['orders'] === []) {
            echo '  (none yet: place an order on the online store)'.PHP_EOL;
        }
        break;

    case 'sell':
        stock($args, 'sale');
        break;

    case 'receive':
        stock($args, 'received');
        break;

    case 'count':
        stock($args, 'count', set: true);
        break;

    case 'price':
        $item = $args[0] ?? fail('Which item?');
        $price = isset($args[1]) && is_numeric($args[1]) ? round((float) $args[1], 2) : fail('What price, including GST?');
        $product = demo_transaction(function (array &$data) use ($item, $price) {
            if (! isset($data['products'][$item])) {
                fail("No item {$item}");
            }
            $data['products'][$item]['price_incl_tax'] = $price;
            $data['products'][$item]['price_excl_tax'] = null;
            $data['products'][$item]['updated_at'] = demo_now();

            return $data['products'][$item];
        });
        echo "{$item} now sells at Rs {$price} including GST.".PHP_EOL;
        echo NEXT_SYNC.PHP_EOL;
        break;

    case 'add':
        $name = $args[0] ?? fail('Usage: add "Name" <price incl. GST> [SKU] [opening stock]');
        $price = isset($args[1]) && is_numeric($args[1]) ? (float) $args[1] : fail('What price, including GST?');
        $product = demo_transaction(function (array &$data) use ($name, $price, $args) {
            $id = demo_next_id($data, 'product', 'ITM-');
            $data['products'][$id] = [
                'external_id' => $id, 'name' => $name, 'brand' => null, 'category' => 'New arrivals', 'category_2' => null,
                'category_3' => null, 'category_4' => null, 'is_active' => true, 'mrp' => null, 'tax_rate' => 18,
                'hsn_code' => null, 'price_incl_tax' => $price, 'price_excl_tax' => null, 'sku' => $args[2] ?? null,
                'unit' => 'pcs', 'variants' => [], 'updated_at' => demo_now(),
                'on_hand' => (int) ($args[3] ?? 0), 'stock_reason' => 'received', 'stock_note' => 'Opening stock', 'stock_updated_at' => demo_now(),
            ];

            return $data['products'][$id];
        });
        echo "Added {$product['external_id']}: {$name}.".PHP_EOL;
        echo NEXT_SYNC.PHP_EOL;
        break;

    case 'dispatch':
        order_status($args, 'shipped');
        break;

    case 'deliver':
        order_status($args, 'delivered');
        break;

    case 'reset':
        @unlink(DEMO_DATA);
        demo_transaction(fn (array &$data) => null);
        echo 'Back to the sample data. The agent sends everything again on its next full read.'.PHP_EOL;
        break;

    default:
        echo <<<'HELP'
        Demo shop counter. The agent picks every change up on its next cycle (every 5 minutes).

          list                                  items, stock, customers and web orders
          sell <item> [variant SKU] <qty>       a counter sale
          receive <item> [variant SKU] <qty> [note]   goods received
          count <item> [variant SKU] <qty>      a stock count: sets the quantity
          price <item> <price incl. GST>        change a price
          add "<name>" <price> [SKU] [stock]    add an item
          dispatch <order number> [carrier] [tracking number]
          deliver <order number>
          reset                                 back to the sample data

        HELP;
}
