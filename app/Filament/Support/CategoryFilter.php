<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The "Category" filter for products and the reports built on them.
 *
 * Picking a category finds every product listed under it, as its main category or as a tag,
 * including the categories below it ("Men" finds Men › Shirts › Formal).
 */
class CategoryFilter
{
    /** On a table of products. */
    public static function make(string $name = 'categories'): SelectFilter
    {
        return self::filter($name, fn (Builder $query, callable $scope) => $scope($query));
    }

    /**
     * On a table of something that reaches products through a relation: "product" for stock
     * movements, "items.product" for orders (an order with any such product).
     */
    public static function through(string $relation, string $name = 'category'): SelectFilter
    {
        return self::filter($name, fn (Builder $query, callable $scope) => $query->whereHas($relation, fn (Builder $products) => $scope($products)));
    }

    /** @param  callable(Builder, callable(Builder): Builder): Builder  $apply */
    private static function filter(string $name, callable $apply): SelectFilter
    {
        return SelectFilter::make($name)
            ->label('Category')
            ->searchable()
            // In tree order, each name with the categories above it on the right, as in the
            // product form's category boxes.
            ->options(fn () => self::treeOptionsHtml())
            ->modifyFormFieldUsing(fn (Select $select) => $select->allowHtml())
            // The chip is plain text ("Category: Men › Shirts"): it would show the option's markup.
            ->indicateUsing(function (array $state): array {
                $category = filled($state['value'] ?? null) ? Category::query()->with(Category::PATH)->find((int) $state['value']) : null;

                return $category ? [Indicator::make('Category: '.$category->pathName())] : [];
            })
            ->query(function (Builder $query, array $data) use ($apply): Builder {
                $scope = self::scopeFor((string) ($data['value'] ?? ''));

                return $scope ? $apply($query, $scope) : $query;
            });
    }

    /** @return (callable(Builder): Builder)|null the product query for an option value */
    private static function scopeFor(string $value): ?callable
    {
        if ($value === '') {
            return null;
        }

        $category = Category::query()->find((int) $value);
        if (! $category) {
            return null;
        }

        $ids = [$category->getKey(), ...$category->descendantIds()];

        return fn (Builder $products) => $products->inCategories($ids);
    }

    /** @return array<int, string> every category, "Men › Shirts" for one below another (also SEO → Search listings) */
    public static function treeOptions(): array
    {
        return self::tree()->map(fn (Category $c) => $c->pathName())->all();
    }

    /**
     * The same list as treeOptions(), each option its own name with the categories above it on
     * the right (Fields::categoryOption), for a select with allowHtml().
     *
     * @return array<int, string>
     */
    public static function treeOptionsHtml(): array
    {
        return self::tree()->map(fn (Category $c) => Fields::categoryOption($c->name, $c->namesAbove()))->all();
    }

    /**
     * Every category keyed by id, in tree order: each one followed by those below it
     * (Men, Men › Pants, Men › Shirts, Women).
     *
     * @return Collection<int, Category>
     */
    private static function tree(): Collection
    {
        return Category::query()->with(Category::PATH)->get()
            ->keyBy(fn (Category $c) => $c->getKey())
            ->sort(fn (Category $a, Category $b) => strnatcasecmp($a->pathName(), $b->pathName()))
            ->toBase();
    }
}
