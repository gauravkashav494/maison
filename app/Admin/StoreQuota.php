<?php

namespace App\Admin;

use App\Models\Product;
use App\Models\Storefront;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Plan limits for one store: how many products it may hold and how much uploaded media it
 * may keep. Both limits are set by the super admin (Platform → Stores); null means unlimited.
 *
 * Storage is measured from the disk, so deletes free space immediately and the number cannot
 * drift from reality. Uploads land in stores/<slug>/… (see Fields::image), and the folder named
 * after the template is still counted so files uploaded before stores existed are not lost.
 */
class StoreQuota
{
    private const CACHE_TTL = 30;

    public function __construct(public readonly Storefront $store) {}

    public static function for(Storefront $store): self
    {
        return new self($store);
    }

    /** Quota of the store the admin session is working in, or null when on "All stores". */
    public static function current(): ?self
    {
        $store = app(StoreContext::class)->storefront();

        return $store ? new self($store) : null;
    }

    // ---- Products ---------------------------------------------------------

    public function productCount(): int
    {
        $tenants = app(\App\Stores\TenantManager::class);

        return $tenants->isolated()
            ? (int) $tenants->forStore($this->store, fn () => Product::withoutGlobalScopes()->count())
            : Product::withoutGlobalScopes()->where('template', $this->store->template)->count();
    }

    public function productLimit(): ?int
    {
        return $this->store->product_limit;
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

    // ---- Storage ----------------------------------------------------------

    /** Directories on the public disk that belong to this store. */
    public function directories(): array
    {
        return ['stores/'.$this->store->slug, $this->store->template];
    }

    public function storageUsed(): int
    {
        return Cache::remember($this->cacheKey(), self::CACHE_TTL, function () {
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
        Cache::forget($this->cacheKey());
    }

    public function storageLimit(): ?int
    {
        return $this->store->storage_limit_mb === null ? null : $this->store->storage_limit_mb * 1024 * 1024;
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

    private function cacheKey(): string
    {
        return 'store.storage.'.$this->store->id;
    }
}
