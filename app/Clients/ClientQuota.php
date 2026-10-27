<?php

namespace App\Clients;

use App\Models\Client;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * What one client is allowed to hold: products, product images and uploaded media.
 *
 * Limits come from the client's plan and can be overridden per client (Platform → Clients),
 * so a paid plan only has to change a number. Null means unlimited; the image allowance
 * falls back to config('clients.default_image_limit'), which is 50.
 *
 * Counts are measured from the data itself — products from the client's own database, images
 * from the products' image lists (the same file referenced twice counts once) and storage
 * from the disk — so a delete frees the allowance immediately and the numbers cannot drift.
 */
class ClientQuota
{
    private const CACHE_TTL = 30;

    public function __construct(public readonly Client $client) {}

    public static function for(Client $client): self
    {
        return new self($client);
    }

    /** Quota of the client the session is working in, or null when across all clients. */
    public static function current(): ?self
    {
        $client = app(ClientContext::class)->client();

        return $client ? new self($client) : null;
    }

    /** Run a query inside this client's own database (and without the request's scopes). */
    private function inClient(callable $callback): mixed
    {
        $databases = app(ClientDatabase::class);

        return $databases->isolated()
            ? $databases->forClient($this->client, $callback)
            : $callback($this->client);
    }

    private function products()
    {
        $query = Product::withoutGlobalScopes();

        // In a shared database the client column is what separates the clients.
        return app(ClientDatabase::class)->isolated() ? $query : $query->where('client_id', $this->client->id);
    }

    // ---- Products ---------------------------------------------------------

    public function productCount(): int
    {
        return (int) $this->inClient(fn () => $this->products()->count());
    }

    public function productLimit(): ?int
    {
        return $this->client->productLimit();
    }

    public function productsLeft(): ?int
    {
        return $this->productLimit() === null ? null : max(0, $this->productLimit() - $this->productCount());
    }

    public function atProductLimit(): bool
    {
        return $this->productLimit() !== null && $this->productCount() >= $this->productLimit();
    }

    public function productPercent(): ?int
    {
        return $this->productLimit() ? (int) min(100, round($this->productCount() / $this->productLimit() * 100)) : null;
    }

    // ---- Product images ---------------------------------------------------

    /**
     * Distinct image files used by this client's products, including any held on a variant
     * (imported data can carry those; the admin form only sets product-level images).
     * Counting distinct paths means re-using one photo on two products costs one image, and
     * the same photo listed twice on one product is not counted twice either.
     */
    public function imageCount(): int
    {
        return Cache::remember($this->cacheKey('images'), self::CACHE_TTL, fn () => count($this->imagePaths()));
    }

    /** @return array<string> */
    public function imagePaths(): array
    {
        return $this->inClient(function () {
            $paths = [];
            $columns = array_values(array_filter(['images', 'variants'], fn ($column) => Schema::connection((new Product)->getConnectionName())->hasColumn('products', $column)));
            $this->products()->select($columns)->cursor()->each(function (Product $product) use (&$paths) {
                foreach ((array) $product->images as $path) {
                    if (filled($path)) {
                        $paths[(string) $path] = true;
                    }
                }
                foreach ((array) $product->variants as $variant) {
                    foreach ((array) ($variant['images'] ?? []) as $path) {
                        if (filled($path)) {
                            $paths[(string) $path] = true;
                        }
                    }
                }
            });

            return array_keys($paths);
        });
    }

    public function imageLimit(): ?int
    {
        return $this->client->imageLimit();
    }

    public function imagesLeft(): ?int
    {
        return $this->imageLimit() === null ? null : max(0, $this->imageLimit() - $this->imageCount());
    }

    public function atImageLimit(): bool
    {
        return $this->imageLimit() !== null && $this->imageCount() >= $this->imageLimit();
    }

    /**
     * Would saving this set of images (for one product) take the client past the limit?
     *
     * A client that is already over its allowance — seeded or imported content, or a limit
     * lowered after the fact — can still edit its products, as long as the save does not add
     * to the count. Only growth past the limit is refused, so the allowance never locks an
     * account out of its own catalogue.
     *
     * @param  array<string>  $paths      images the save keeps, as stored paths
     * @param  array<string>  $replacing  images the record holds today; they free their slot
     * @param  int  $added                files being uploaded now, not yet named
     */
    public function wouldExceedImages(array $paths, array $replacing = [], int $added = 0): bool
    {
        if ($this->imageLimit() === null) {
            return false;
        }
        $existing = array_fill_keys($this->imagePaths(), true);
        $before = count($existing);

        foreach ($replacing as $path) {
            unset($existing[(string) $path]);
        }
        foreach ($paths as $path) {
            if (filled($path)) {
                $existing[(string) $path] = true;
            }
        }
        $after = count($existing) + $added;

        return $after > $this->imageLimit() && $after > $before;
    }

    public function imagePercent(): ?int
    {
        return $this->imageLimit() ? (int) min(100, round($this->imageCount() / $this->imageLimit() * 100)) : null;
    }

    public function imageLabel(): string
    {
        return number_format($this->imageCount()).' / '.($this->imageLimit() === null ? 'unlimited' : number_format($this->imageLimit()));
    }

    // ---- Storage ----------------------------------------------------------

    /** Directories on the public disk that belong to this client. */
    public function directories(): array
    {
        return array_values(array_unique(array_filter([
            'clients/'.$this->client->slug,
            'stores/'.($this->client->storefront?->slug ?? ''),
            (string) $this->client->templateId(),
        ])));
    }

    public function storageUsed(): int
    {
        return Cache::remember($this->cacheKey('storage'), self::CACHE_TTL, function () {
            $disk = Storage::disk('public');
            $bytes = 0;
            foreach ($this->directories() as $dir) {
                if (! $disk->directoryExists($dir)) {
                    continue;
                }
                foreach ($disk->allFiles($dir) as $file) {
                    $bytes += $disk->size($file);
                }
            }

            return $bytes;
        });
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey('storage'));
        Cache::forget($this->cacheKey('images'));
    }

    public function storageLimit(): ?int
    {
        $mb = $this->client->storageLimitMb();

        return $mb === null ? null : $mb * 1024 * 1024;
    }

    public function storageLeft(): ?int
    {
        return $this->storageLimit() === null ? null : max(0, $this->storageLimit() - $this->storageUsed());
    }

    public function storageFull(): bool
    {
        return $this->storageLimit() !== null && $this->storageUsed() >= $this->storageLimit();
    }

    /** Would adding this many bytes exceed the limit? */
    public function wouldExceedStorage(int $bytes): bool
    {
        return $this->storageLimit() !== null && ($this->storageUsed() + $bytes) > $this->storageLimit();
    }

    public function storagePercent(): ?int
    {
        return $this->storageLimit() ? (int) min(100, round($this->storageUsed() / $this->storageLimit() * 100)) : null;
    }

    // ---- Presentation -----------------------------------------------------

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        foreach ([['GB', 1073741824], ['MB', 1048576], ['KB', 1024]] as [$unit, $size]) {
            if ($bytes >= $size) {
                return round($bytes / $size, $bytes >= $size * 10 ? 0 : 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }

    public function productLabel(): string
    {
        return number_format($this->productCount()).' / '.($this->productLimit() === null ? 'unlimited' : number_format($this->productLimit()));
    }

    public function storageLabel(): string
    {
        return self::formatBytes($this->storageUsed()).' / '.($this->storageLimit() === null ? 'unlimited' : self::formatBytes($this->storageLimit()));
    }

    private function cacheKey(string $kind): string
    {
        return 'client.'.$kind.'.'.$this->client->id;
    }
}
