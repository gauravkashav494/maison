<?php

namespace App\Console\Commands;

use App\Clients\ClientDatabase;
use App\Models\Client;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Support\TaxPrices;
use Illuminate\Console\Command;

/**
 * Sets up the tax master for existing stores. For each client and store (template):
 *   - a tax for every rate its products use, named e.g. "GST 18%";
 *   - each product without a tax is given the tax for its rate (its own, else the store's);
 *   - each product's and variant's price including and excluding tax is filled in.
 * Uses each client's own tax settings. Safe to run again: what is already set is left alone.
 */
class TaxesBackfill extends Command
{
    protected $signature = 'taxes:backfill {subdomain? : Only this client}';

    protected $description = 'Create taxes from the rates products use, link the products, and fill in their prices incl. and excl. tax';

    public function handle(ClientDatabase $databases): int
    {
        $clients = $databases->clients()
            ->when($this->argument('subdomain'), fn ($all) => $all->where('subdomain', $this->argument('subdomain')));

        foreach ($clients as $client) {
            $checkout = (Setting::forClient($client->id, 'checkout') ?? []) + (array) config('client_defaults.checkout');
            $inclusive = ($checkout['tax_mode'] ?? 'exclusive') === 'inclusive';
            $storeRate = round((float) ($checkout['tax_rate'] ?? 0), 2);
            $label = 'GST';

            $result = $databases->forClientOr($client, fn () => $this->backfill($client, $inclusive, $storeRate, $label), null);

            $result === null
                ? $this->warn("{$client->name}: database not ready, skipped.")
                : $this->line("{$client->name}: {$result['taxes']} taxes added, {$result['products']} products updated.");
        }

        return self::SUCCESS;
    }

    /** @return array{taxes: int, products: int} */
    private function backfill(Client $client, bool $inclusive, float $storeRate, string $label): array
    {
        $added = 0;
        $updated = 0;
        $taxes = [];

        // The tax for a rate in a store, created if it has none yet.
        $taxFor = function (?string $template, float $rate) use ($client, $label, &$taxes, &$added): Tax {
            $key = ($template ?? '').'|'.number_format($rate, 2, '.', '');

            return $taxes[$key] ??= Tax::withoutGlobalScopes()
                ->where('client_id', $client->id)->where('template', $template)->where('rate', $rate)
                ->orderBy('id')->first()
                ?? tap(Tax::withoutGlobalScopes()->create([
                    'client_id' => $client->id,
                    'template' => $template,
                    'name' => $rate > 0 ? $label.' '.Tax::rateLabel($rate) : 'No tax (0%)',
                    'rate' => $rate,
                ]), function () use (&$added) {
                    $added++;
                });
        };

        $templates = Product::withoutGlobalScopes()->withTrashed()->where('client_id', $client->id)
            ->distinct()->pluck('template');

        foreach ($templates as $template) {
            Product::withoutGlobalScopes()->withTrashed()
                ->where('client_id', $client->id)->where('template', $template)
                ->orderBy('id')
                ->each(function (Product $product) use ($template, $storeRate, $inclusive, $taxFor, &$updated) {
                    $changes = [];

                    $tax = $product->tax_id
                        ? Tax::withoutGlobalScopes()->withTrashed()->find($product->tax_id)
                        : null;
                    if (! $tax) {
                        $tax = $taxFor($template, $product->tax_rate !== null ? round((float) $product->tax_rate, 2) : $storeRate);
                        $changes['tax_id'] = $tax->getKey();
                    }
                    $rate = (float) $tax->rate;
                    if ($product->tax_rate === null || abs((float) $product->tax_rate - $rate) > 0.001) {
                        $changes['tax_rate'] = $rate;
                    }

                    $prices = TaxPrices::sync([
                        'price' => $product->price,
                        'price_incl_tax' => $product->price_incl_tax,
                        'price_excl_tax' => $product->price_excl_tax,
                    ], $rate, $inclusive);
                    foreach (['price_incl_tax', 'price_excl_tax'] as $key) {
                        if ($prices[$key] !== $product->{$key}) {
                            $changes[$key] = $prices[$key];
                        }
                    }

                    // Variants follow the product's tax.
                    if (is_array($product->variants) && $product->variants !== []) {
                        $variants = array_map(
                            fn ($row) => is_array($row) ? TaxPrices::sync(array_diff_key($row, ['tax_rate' => true]), $rate, $inclusive) : $row,
                            $product->variants,
                        );
                        if ($variants !== $product->variants) {
                            $changes['variants'] = json_encode($variants);
                        }
                    }

                    if ($changes !== []) {
                        // A plain update: saving the model would run its other save hooks too.
                        $product->newQueryWithoutScopes()->whereKey($product->getKey())->update($changes);
                        $updated++;
                    }
                });
        }

        return ['taxes' => $added, 'products' => $updated];
    }
}
