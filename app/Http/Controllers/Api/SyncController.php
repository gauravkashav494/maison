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
            'external_id' => self::EXTERNAL_ID,
            'sku' => ['nullable', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', ...self::MONEY],
            'compare_at_price' => ['nullable', ...self::MONEY],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'variants' => ['nullable', 'array', 'max:'.self::MAX_VARIANTS],
            'variants.*.sku' => ['required', 'string', 'max:191', 'distinct'],
            'variants.*.options' => ['required', 'array', 'min:1', 'max:5'],
            'variants.*.options.*' => ['required', 'string', 'max:100'],
            'variants.*.price' => ['nullable', ...self::MONEY],
        ], 'external_id', fn (array $item) => $inbound->product($item));
    }

    public function stock(Request $request): JsonResponse
    {
        ['valid' => $valid, 'results' => $results] = $this->validated($request, [
            'sku' => ['required', 'string', 'max:191'],
            'on_hand' => ['required', 'integer', 'min:0'],
            'price' => ['nullable', ...self::MONEY],
            // Why the stock changed, as on the Adjust stock screen; "From local system" when left out.
            'reason' => ['nullable', 'string', Rule::in(array_keys(StockMovement::LOCAL_REASONS))],
            'note' => ['nullable', 'string', 'max:255'],
        ], 'sku');

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
    private function validated(Request $request, array $rules, string $key): array
    {
        $items = $request->validate(['items' => ['required', 'array', 'max:'.self::MAX_BATCH]])['items'];

        $valid = [];
        $results = [];
        foreach ($items as $item) {
            $validator = Validator::make(is_array($item) ? $item : [], $rules);
            if ($validator->fails()) {
                $results[] = SyncResult::rejected(is_array($item) && is_scalar($item[$key] ?? null) ? $item[$key] : null, $validator->errors()->first());

                continue;
            }
            $valid[] = $validator->validated();
        }

        return ['valid' => $valid, 'results' => $results];
    }
}
