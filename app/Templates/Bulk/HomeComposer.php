<?php

namespace App\Templates\Bulk;

use App\Models\Customer;
use App\Models\Product;
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
            'recentOrders' => $this->recentOrders(),
        ]);
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
