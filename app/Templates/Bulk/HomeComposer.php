<?php

namespace App\Templates\Bulk;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Supplies the home page's bulk order section — the order form, the paste-a-list tab and
 * "Order again". The form searches every orderable line in the browser, so it needs the whole
 * catalogue's lines up front.
 */
class HomeComposer
{
    /** Above this many products the form searches only the first ones; a wholesaler's list rarely is. */
    private const INDEX_LIMIT = 3000;

    public function compose(View $view): void
    {
        $all = Product::with('category')->active()->orderBy('sort_order')->orderBy('name')->limit(self::INDEX_LIMIT)->get();

        $view->with([
            'quickIndex' => OrderLines::index(OrderLines::forMany($all)),
            'categoryTree' => $this->categoryTree($all),
            'recentOrders' => $this->recentOrders(),
        ]);
    }

    /**
     * The order form's Category filter: every active category in menu order, each child under its
     * parent (depth for the indent), with the ids of the products listed in it or anywhere below
     * it — so choosing Men shows Shirts, Pants and the rest. Empty categories are listed too.
     *
     * @return list<array{id:int, parent:?int, name:string, depth:int, products:list<int>}>
     */
    private function categoryTree($products): array
    {
        $productIds = $products->pluck('id')->all();
        // Every category a product is listed under (the main one is always among them).
        $listed = [];
        foreach ($products as $product) {
            if ($product->category_id) {
                $listed[$product->category_id][$product->id] = true;
            }
        }
        $pivot = $products->first()?->categories()->getTable();
        if ($pivot && $productIds !== []) {
            foreach (DB::table($pivot)->whereIn('product_id', $productIds)->get(['category_id', 'product_id']) as $row) {
                $listed[$row->category_id][$row->product_id] = true;
            }
        }

        $byParent = Category::active()->ordered()->get(['id', 'parent_id', 'name'])
            ->groupBy(fn ($c) => (int) $c->parent_id);
        $rows = [];
        $seen = [];
        // Returns the product ids in this category's subtree, adding its row before its children.
        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, &$seen, $byParent, $listed): void {
            foreach ($byParent[$parentId] ?? [] as $c) {
                if (isset($seen[$c->id]) || $depth > 5) {
                    continue; // a loop in the data, or deeper than any store goes
                }
                $seen[$c->id] = true;
                $at = count($rows);
                $rows[] = ['id' => $c->id, 'parent' => $parentId ?: null, 'name' => $c->name, 'depth' => $depth, 'products' => array_keys($listed[$c->id] ?? [])];
                $walk((int) $c->id, $depth + 1);
                // Fold the children's products into this row.
                for ($i = $at + 1; $i < count($rows) && $rows[$i]['depth'] > $depth; $i++) {
                    $rows[$at]['products'] = array_merge($rows[$at]['products'], $rows[$i]['products']);
                }
                $rows[$at]['products'] = array_values(array_unique($rows[$at]['products']));
            }
        };
        $walk(0, 0);

        return $rows;
    }

    /**
     * The signed-in buyer's last few orders, each as "CODE, QTY" lines that refill the form.
     * Lines from products without a code cannot be matched again, so they are counted, not listed.
     */
    private function recentOrders(): array
    {
        $user = auth()->user();
        if (! $user instanceof Customer) {
            return [];
        }

        return $user->orders()->with('items')->limit(5)->get()
            ->map(function ($order) {
                $coded = $order->items->filter(fn ($item) => filled($item->variant_sku ?: $item->sku));

                return [
                    'number' => $order->number,
                    'date' => $order->created_at?->format('j M Y'),
                    'lines' => $order->items->count(),
                    'total' => money($order->total),
                    'skipped' => $order->items->count() - $coded->count(),
                    'pad' => $coded->map(fn ($item) => ($item->variant_sku ?: $item->sku).', '.$item->qty)->implode("\n"),
                ];
            })->all();
    }
}
