<?php

namespace App\Http\Controllers\Api;

use App\Clients\ClientContext;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Order;
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

    private const EXTERNAL_ID = ['required', 'string', 'max:191'];

    private function client(): Client
    {
        return app(ClientContext::class)->client();
    }

    public function ping(): JsonResponse
    {
        return response()->json(['client' => $this->client()->subdomain, 'server_time' => now()->toIso8601String()]);
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
            'price' => ['required', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
        ], 'external_id', fn (array $item) => $inbound->product($item));
    }

    public function stock(Request $request): JsonResponse
    {
        ['valid' => $valid, 'results' => $results] = $this->validated($request, [
            'sku' => ['required', 'string', 'max:191'],
            'on_hand' => ['required', 'integer', 'min:0'],
            'price' => ['nullable', 'integer', 'min:0'],
        ], 'sku');

        return response()->json(['results' => [...$results, ...(new SyncInbound($this->client()))->stock($valid)]]);
    }

    public function customers(Request $request): JsonResponse
    {
        $inbound = new SyncInbound($this->client());

        return $this->batch($request, [
            'external_id' => self::EXTERNAL_ID,
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
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
