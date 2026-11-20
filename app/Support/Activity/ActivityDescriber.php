<?php

namespace App\Support\Activity;

use App\Clients\ClientModules;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a model change into one plain sentence for the activity log, written without the actor
 * (the log shows who did it next to it):
 *
 *   added product "Silk Saree"
 *   changed the price of product "Silk Saree" from ₹4,500.00 to ₹3,999.00
 *   marked order #1043 as Dispatched
 *   switched off promo code "DIWALI20"
 *   updated page "About us": title "About" → "About us", SEO description and 2 more
 *
 * Values are shown only for short, safe fields. Passwords, tokens, hints and OTPs are named but
 * never shown; long text, HTML, lists and JSON are named only ("changed the description").
 */
class ActivityDescriber
{
    /** Bookkeeping columns that never make a line on their own. */
    private const IGNORED = [
        'updated_at', 'created_at', 'deleted_at', 'remember_token', 'last_login_at', 'status_history',
        // Worked out from the stock (Product::lowestStock()): the stock change itself is listed.
        'lowest_stock',
        'client_id', 'sync_version', 'sync_acked_version', 'external_id', 'price_incl_tax', 'price_excl_tax',
        'review_count', 'rating', 'invoice_number', 'invoice_fy', 'invoice_seq', 'invoiced_at', 'dispatched_at', 'delivered_at',
        'cancelled_at', 'email_verified_at', 'phone_verified_at', 'used_count', 'unsubscribe_token',
        'password_hint', // follows the password, which is logged (by name) on its own
    ];

    /** Named, never shown. */
    private const SECRET = '/password|token|secret|otp|api_key|hint/i';

    private const TYPES = [
        'ServiceRequest' => 'booking',
        'Post' => 'journal post',
        'Store' => 'store location',
        'Coupon' => 'promo code',
        'MenuItem' => 'menu link',
        'ClientUser' => 'staff account',
        'ClientRole' => 'role',
        'Faq' => 'FAQ',
        'StockMovement' => 'stock movement',
        'User' => 'platform account',
    ];

    private const LABELS = [
        'meta_title' => 'SEO title',
        'meta_description' => 'SEO description',
        'og_image' => 'social image',
        'canonical_url' => 'canonical URL',
        'noindex' => 'hide from search engines',
        'show_in_menu' => 'feature in menu & homepage',
        'compare_at_price' => 'compare-at price',
        'sku' => 'SKU',
        'extra_category_1' => 'category tag 1',
        'extra_category_2' => 'category tag 2',
        'url' => 'URL',
        'video_url' => 'video URL',
        'maps_url' => 'map link',
        'min_subtotal' => 'minimum order',
        'usage_limit' => 'total uses allowed',
        'per_customer_limit' => 'uses per customer',
        'starts_at' => 'start date',
        'ends_at' => 'end date',
        'client_role_id' => 'role',
        'admin_notes' => 'internal notes',
        'sort_order' => 'position',
        'max_qty' => 'maximum quantity',
        'parent_id' => 'parent',
        'is_new' => 'new arrival',
        'is_best_seller' => 'best seller',
        'is_veg' => 'vegetarian',
        'opens_in_new_tab' => 'opens in a new tab',
        'admin_modules' => 'modules',
    ];

    /** Foreign keys whose relation is not simply the column without "_id". */
    private const RELATIONS = ['brand_id' => 'brandRecord'];

    private const MONEY = ['price', 'compare_at_price', 'subtotal', 'discount', 'tax', 'total', 'shipping_cost', 'min_subtotal'];

    // ---- What -------------------------------------------------------------------------------

    /** "product", "promo code", "booking". */
    public function type(Model|string $model): string
    {
        $base = class_basename($model);

        return self::TYPES[$base] ?? Str::of($base)->snake(' ')->toString();
    }

    public function pluralType(Model|string $model, int $count): string
    {
        $type = $this->type($model);

        return $count === 1 ? $type : ($type === 'FAQ' ? 'FAQs' : Str::plural($type));
    }

    /** The record's own name: the order number, the coupon code, the product name … */
    public function name(Model $model): string
    {
        $attributes = $model->getAttributes();

        foreach (['number', 'reference', 'code', 'title', 'name', 'label', 'question', 'subject', 'email'] as $key) {
            if (filled($attributes[$key] ?? null) && is_scalar($attributes[$key])) {
                return Str::limit(strip_tags((string) $attributes[$key]), 80);
            }
        }

        return '#'.$model->getKey();
    }

    /** product "Silk Saree", order #1043, booking SR-2041. */
    public function reference(Model $model): string
    {
        $name = $this->name($model);
        $attributes = $model->getAttributes();
        $unquoted = filled($attributes['number'] ?? null) || filled($attributes['reference'] ?? null) || str_starts_with($name, '#');

        if (filled($attributes['number'] ?? null) && ctype_digit((string) $attributes['number'])) {
            $name = '#'.$name;
        }

        return $this->type($model).' '.($unquoted ? $name : '"'.$name.'"');
    }

    /** The menu section the record belongs to, for filtering ("Products", "Promo codes", "Team"). */
    public function area(Model|string $model): string
    {
        $class = is_string($model) ? $model : $model::class;

        return match (class_basename($class)) {
            'ClientUser', 'ClientRole' => 'Team',
            'MenuItem' => 'Navigation',
            'Client', 'Plan', 'Storefront', 'User' => 'Platform',
            default => ClientModules::get((string) ClientModules::forModel($class))['label']
                ?? Str::of(class_basename($class))->snake(' ')->plural()->ucfirst()->toString(),
        };
    }

    // ---- Sentences --------------------------------------------------------------------------

    public function created(Model $model): string
    {
        return 'added '.$this->reference($model);
    }

    public function deleted(Model $model, bool $permanently): string
    {
        if ($permanently) {
            return 'permanently deleted '.$this->reference($model);
        }

        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? 'moved '.$this->reference($model).' to the trash'
            : 'deleted '.$this->reference($model);
    }

    public function restored(Model $model): string
    {
        return 'restored '.$this->reference($model).' from the trash';
    }

    /** The fields that changed in an update and are worth a line (bookkeeping columns left out). */
    public function meaningfulChanges(Model $model): array
    {
        $ignored = self::IGNORED;
        if (method_exists($model, 'templateColumn') && $model::templateColumn() !== 'template') {
            // Pages keep the store in `storefront_template`; their `template` is the page layout.
            $ignored[] = $model::templateColumn();
        } elseif (method_exists($model, 'templateColumn')) {
            $ignored[] = 'template';
        }

        return array_values(array_diff(array_keys($model->getChanges()), $ignored));
    }

    /**
     * @param  array<string>  $fields  changed columns (from meaningfulChanges)
     * @return array{0: string, 1: array<int, array{field: string, label: string, old: ?string, new: ?string}>}
     */
    public function updated(Model $model, array $fields, bool $self = false): array
    {
        $changes = array_map(fn (string $field) => $this->change($model, $field), $fields);
        $byField = array_column($changes, null, 'field');
        $ref = $self ? 'their own account' : $this->reference($model);

        // A status move reads as what happened to the record; anything else saved with it follows.
        if (isset($byField['status']) && $byField['status']['new'] !== null) {
            $rest = array_values(array_filter($changes, fn (array $c) => $c['field'] !== 'status'));
            $sentence = 'marked '.$ref.' as '.$byField['status']['new'];

            return [$rest === [] ? $sentence : $sentence.' and updated '.$this->list($rest), $changes];
        }

        if (count($changes) === 1) {
            return [$this->single($model, $changes[0], $ref, $self), $changes];
        }

        return ['updated '.$ref.': '.$this->list($changes), $changes];
    }

    /** "updated Site settings: store name, logo and 3 more". */
    public function settings(string $page, array $changes): string
    {
        return 'updated '.$page.': '.$this->list($changes);
    }

    /**
     * One line for a bulk action over many records of one kind.
     *
     * @param  array<int, array<string, mixed>>  $entries  log rows of one action and one type
     */
    public function bulk(array $entries, string $modelClass): string
    {
        $count = count($entries);
        $types = $count.' '.$this->pluralType($modelClass, $count);
        $action = $entries[0]['action'];

        $statuses = array_unique(array_map(
            fn (array $e) => collect($e['changes'] ?? [])->firstWhere('field', 'status')['new'] ?? null,
            $entries,
        ));

        $verb = match (true) {
            $action === 'created' => 'added '.$types,
            $action === 'deleted' => 'moved '.$types.' to the trash',
            $action === 'deleted permanently' => 'permanently deleted '.$types,
            $action === 'restored' => 'restored '.$types.' from the trash',
            $action === 'updated' && count($statuses) === 1 && $statuses[0] !== null => 'marked '.$types.' as '.$statuses[0],
            default => $action.' '.$types,
        };

        $names = array_column($entries, 'subject');

        return $verb.': '.implode(', ', array_slice($names, 0, 3)).($count > 3 ? ' and '.($count - 3).' more' : '');
    }

    // ---- Pieces -----------------------------------------------------------------------------

    /** @return array{field: string, label: string, old: ?string, new: ?string, bool?: bool} */
    public function change(Model $model, string $field): array
    {
        $change = ['field' => $field, 'label' => $this->label($field), 'old' => null, 'new' => null];

        if (preg_match(self::SECRET, $field)) {
            return $change;
        }

        try {
            if ($this->isBoolean($model, $field)) {
                return array_merge($change, [
                    'old' => $model->getOriginal($field) ? 'on' : 'off',
                    'new' => $model->getAttribute($field) ? 'on' : 'off',
                    'bool' => (bool) $model->getAttribute($field),
                ]);
            }

            $change['old'] = $this->display($model, $field, $model->getOriginal($field), $model->getRawOriginal($field));
            $change['new'] = $this->display($model, $field, $model->getAttribute($field), $model->getAttributes()[$field] ?? null);
        } catch (Throwable) {
            // A value that cannot be shown is simply named.
            $change['old'] = $change['new'] = null;
        }

        // Shown only when both sides can be shown, so a long text never appears half.
        if ($change['old'] === false || $change['new'] === false) {
            $change['old'] = $change['new'] = null;
        }

        return $change;
    }

    public function label(string $field): string
    {
        if (isset(self::LABELS[$field])) {
            return self::LABELS[$field];
        }

        return Str::of($field)->replaceMatches('/^is_|^has_|_id$/', '')->replace('_', ' ')->toString();
    }

    private function single(Model $model, array $change, string $ref, bool $self): string
    {
        $field = $change['field'];
        $label = $change['label'];

        if (array_key_exists('bool', $change)) {
            $on = $change['bool'];

            return match ($field) {
                'is_active' => ($on ? 'switched on ' : 'switched off ').$ref,
                'is_approved' => ($on ? 'approved ' : 'withdrew approval of ').$ref,
                'is_read' => 'marked '.$ref.' as '.($on ? 'read' : 'unread'),
                default => ($on ? 'turned on "' : 'turned off "').$label.'" for '.$ref,
            };
        }

        if ($self && preg_match(self::SECRET, $field)) {
            return 'changed their '.$label;
        }

        if ($change['old'] === null && $change['new'] === null) {
            return 'changed the '.$label.' of '.$ref;
        }

        return match (true) {
            $change['old'] === '' => 'set the '.$label.' of '.$ref.' to '.$change['new'],
            $change['new'] === '' => 'cleared the '.$label.' of '.$ref,
            default => 'changed the '.$label.' of '.$ref.' from '.$change['old'].' to '.$change['new'],
        };
    }

    /** "price ₹4,500.00 → ₹3,999.00, featured on, description and 2 more". */
    private function list(array $changes): string
    {
        $parts = array_map(function (array $c) {
            if (array_key_exists('bool', $c)) {
                return $c['label'].' '.($c['bool'] ? 'on' : 'off');
            }
            if ($c['old'] === null && $c['new'] === null) {
                return $c['label'];
            }

            return $c['label'].' '.($c['old'] === '' ? 'empty' : $c['old']).' → '.($c['new'] === '' ? 'empty' : $c['new']);
        }, $changes);

        $shown = array_slice($parts, 0, 3);
        $more = count($parts) - count($shown);

        if ($more > 0) {
            return implode(', ', $shown).' and '.$more.' more';
        }

        return count($shown) > 1
            ? implode(', ', array_slice($shown, 0, -1)).' and '.end($shown)
            : (string) ($shown[0] ?? 'nothing');
    }

    private function isBoolean(Model $model, string $field): bool
    {
        return $model->hasCast($field, ['bool', 'boolean'])
            || preg_match('/^(is|has|show|opens)_|^noindex$|^prices_include_tax$/', $field) === 1;
    }

    /**
     * A value as a person reads it: "" for empty, false when it should not be shown at all.
     */
    private function display(Model $model, string $field, mixed $value, mixed $raw): string|false
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if (str_ends_with($field, '_id')) {
            return $this->relatedName($model, $field, $raw) ?? false;
        }

        if ($field === 'status' || $field === 'payment_status') {
            return $this->statusLabel($model, $field, (string) $raw);
        }

        if ($value instanceof CarbonInterface) {
            return $value->format($value->format('H:i:s') === '00:00:00' ? 'd M Y' : 'd M Y, H:i');
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (! is_scalar($value)) {
            return false; // lists, JSON, images
        }

        if (in_array($field, self::MONEY, true) && is_numeric($value)) {
            return money($value);
        }

        if ($field === 'rate' && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $text = (string) $value;
        if (mb_strlen($text) > 60 || str_contains($text, "\n") || $text !== strip_tags($text)) {
            return false;
        }

        return '"'.$text.'"';
    }

    private function statusLabel(Model $model, string $field, string $value): string
    {
        if ($field === 'status') {
            $options = method_exists($model, 'statusOptions')
                ? $model::statusOptions()
                : (defined($model::class.'::STATUSES') ? constant($model::class.'::STATUSES') : []);

            return $options[$value] ?? Str::headline($value);
        }

        return $value === 'cod' ? 'Pay on delivery' : Str::headline($value);
    }

    private function relatedName(Model $model, string $field, mixed $id): ?string
    {
        $relation = self::RELATIONS[$field] ?? Str::camel(substr($field, 0, -3));
        if (! method_exists($model, $relation)) {
            return null;
        }

        $related = $model->{$relation}();
        if (! $related instanceof BelongsTo) {
            return null;
        }

        $record = $related->getRelated()->newQuery()->find($id);

        return $record ? '"'.$this->name($record).'"' : null;
    }
}
