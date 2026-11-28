<?php

namespace App\Http\Controllers\Api;

use App\Clients\ClientContext;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Order;
use App\Models\StockMovement;
use App\Sync\SyncFeed;
use App\Sync\SyncInbound;
use App\Sync\SyncResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The API the local sync agent calls. See docs/SYNC.md. */
class SyncController extends Controller
{
    private const MAX_BATCH = 500;

    private const MAX_VARIANTS = 200;

    private const EXTERNAL_ID = ['required', 'string', 'max:191'];

    /**
     * A price as `price` (the store's own form, ping's prices_include_tax), or as the local system
     * holds it: `price_incl_tax`, `price_excl_tax` or both, which SyncInbound converts with the
     * product's tax. `price` goes alone.
     *
     * @return array<string, array<int, string>>
     */
    private static function prices(string $prefix = '', bool $required = false): array
    {
        [$price, $incl, $excl] = [$prefix.'price', $prefix.'price_incl_tax', $prefix.'price_excl_tax'];

        return [
            $price => ['nullable', ...($required ? ["required_without_all:{$incl},{$excl}"] : []), "prohibits:{$incl},{$excl}", ...self::MONEY],
            $incl => ['nullable', ...self::MONEY],
            $excl => ['nullable', ...self::MONEY],
        ];
    }

    /** An HSN code as the product form takes it: 4, 6 or 8 digits (spaces allowed, then dropped). */
    private const HSN = 'regex:/^\s*\d{4}(\s*\d{2}){0,2}\s*$/';

    /** Rupees and paise, as the money columns hold them (decimal 12,2). */
    private const MONEY = ['numeric', 'min:0', 'max:9999999999', 'decimal:0,2'];

    private function client(): Client
    {
        return app(ClientContext::class)->client();
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'client' => $this->client()->subdomain,
            'server_time' => now()->toIso8601String(),
            // Which price `price` is: the store's prices are entered including tax, or before it.
            'prices_include_tax' => prices_include_tax(),
        ]);
    }

    public function changes(Request $request, string $resource): JsonResponse
    {
        $query = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_BATCH],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json((new SyncFeed($this->client()))->pending($resource, (int) ($query['limit'] ?? 100), (int) ($query['after_id'] ?? 0)));
    }

    public function acknowledge(Request $request, string $resource): JsonResponse
    {
        $feed = new SyncFeed($this->client());

        return $this->batch($request, [
            'id' => ['required', 'integer', 'min:1'],
            'version' => ['required', 'integer', 'min:1'],
            'external_id' => ['nullable', 'string', 'max:191'],
        ], 'id', fn (array $item) => $feed->acknowledge($resource, $item));
    }

    public function products(Request $request): JsonResponse
    {
        $inbound = new SyncInbound($this->client());

        return $this->batch($request, [
            // In the product form's order (Details, then Pricing & Variants).
            'external_id' => self::EXTERNAL_ID,
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            // As the form's category boxes: a category and a child of it, then two category tags,
            // each any category.
            'category' => ['nullable', 'required_with:category_2,category_3,category_4', 'string', 'max:255'],
            'category_2' => ['nullable', 'string', 'max:255'],
            'category_3' => ['nullable', 'string', 'max:255'],
            'category_4' => ['nullable', 'string', 'max:255'],
            'compare_at_price' => ['nullable', ...self::MONEY],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'hsn_code' => ['nullable', 'string', self::HSN],
            ...self::prices(required: true),
            'sku' => ['nullable', 'string', 'max:191'],
            'variants' => ['nullable', 'array', 'max:'.self::MAX_VARIANTS],
            'variants.*.options' => ['required', 'array', 'min:1', 'max:5'],
            'variants.*.options.*' => ['required', 'string', 'max:100'],
            'variants.*.sku' => ['required', 'string', 'max:191', 'distinct'],
            'variants.*.hsn_code' => ['nullable', 'string', self::HSN],
            ...self::prices(prefix: 'variants.*.'),
        ], 'external_id', fn (array $item) => $inbound->product($item));
    }

    public function stock(Request $request): JsonResponse
    {
        ['valid' => $valid, 'results' => $results] = $this->validated($request, [
            // The product by the local system's own id, its SKU, or both; a variant by its SKU.
            'external_id' => ['nullable', 'required_without:sku', 'string', 'max:191'],
            'sku' => ['nullable', 'required_without:external_id', 'string', 'max:191'],
            'on_hand' => ['required', 'integer', 'min:0'],
            ...self::prices(),
            // Why the stock changed, as on the Adjust stock screen; "From local system" when left out.
            'reason' => ['nullable', 'string', Rule::in(array_keys(StockMovement::LOCAL_REASONS))],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['sku', 'external_id']);

        return response()->json(['results' => [...$results, ...(new SyncInbound($this->client()))->stock($valid)]]);
    }

    public function customers(Request $request): JsonResponse
    {
        $inbound = new SyncInbound($this->client());

        return $this->batch($request, [
            'external_id' => self::EXTERNAL_ID,
            'name' => ['required', 'string', 'max:255'],
            // As in the admin, a customer may be known by phone alone.
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
        ], 'external_id', fn (array $item) => $inbound->customer($item));
    }

    public function orderStatuses(Request $request): JsonResponse
    {
        $inbound = new SyncInbound($this->client());

        return $this->batch($request, [
            'number' => ['required', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(array_keys(Order::statusOptions()))],
            'carrier' => ['nullable', 'string', 'max:191'],
            'tracking_number' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:500'],
        ], 'number', fn (array $item) => $inbound->orderStatus($item));
    }

    private function batch(Request $request, array $rules, string $key, callable $apply): JsonResponse
    {
        ['valid' => $valid, 'results' => $results] = $this->validated($request, $rules, $key);

        return response()->json(['results' => [...$results, ...array_map($apply, $valid)]]);
    }

    /** @return array{valid: array<int, array<string, mixed>>, results: array<int, array<string, mixed>>} */
    /** @param  string|array<int, string>  $key  the field naming each item; the first one filled */
    private function validated(Request $request, array $rules, string|array $key): array
    {
        $items = $request->validate(['items' => ['required', 'array', 'max:'.self::MAX_BATCH]])['items'];

        $valid = [];
        $results = [];
        foreach ($items as $item) {
            $validator = Validator::make(is_array($item) ? $item : [], $rules);
            if ($validator->fails()) {
                $name = null;
                foreach ((array) $key as $field) {
                    if (is_array($item) && is_scalar($item[$field] ?? null) && filled($item[$field])) {
                        $name = $item[$field];
                        break;
                    }
                }
                $results[] = SyncResult::rejected($name, $validator->errors()->first());

                continue;
            }
            $valid[] = $validator->validated();
        }

        return ['valid' => $valid, 'results' => $results];
    }
}
