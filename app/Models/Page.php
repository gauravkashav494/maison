<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use BelongsToTemplate;
    use HasSeo;

    /** pages.template is the page layout; the storefront template lives here. */
    public static function templateColumn(): string
    {
        return 'storefront_template';
    }

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'noindex' => 'boolean', 'data' => 'array'];

    public const TEMPLATES = [
        'default' => 'Editorial page',
        'legal' => 'Legal / policy document',
        'about' => 'About (brand story sections)',
        'contact' => 'Contact (form + details)',
        'faq' => 'FAQ (categorised accordion)',
        'size-guide' => 'Size guide (tables)',
        'care-guide' => 'Care guide (by material)',
        'gift-cards' => 'Gift cards',
        'careers' => 'Careers (open roles)',
        'stores' => 'Store locator',
        'cookies' => 'Cookie policy (with preferences)',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }

    public function getUrlAttribute(): string
    {
        return route('pages.show', $this->slug);
    }
}
