<?php

namespace App\Clients;

use App\Http\Middleware\ResolveTemplate;
use App\Models\Client;
use Illuminate\Support\Facades\Cache;

/**
 * Moving a client to a store that runs another template.
 *
 * Catalogue content (products, categories, pages, posts…) is saved with the template it was
 * made in, and the storefront only shows rows for its own template or for every template. So
 * after a switch, everything the client made would vanish from their site while still showing
 * in admin. This moves those rows to the new template. Rows marked "every template" are left
 * alone, and nothing is deleted.
 */
class ClientTemplateSwitch
{
    /**
     * Retags the client's rows from one template to another.
     *
     * @return array<string, int> rows moved, by model (class basename), only models with any
     */
    public static function moveContent(Client $client, string $from, string $to): array
    {
        if ($from === $to) {
            return [];
        }

        return app(ClientDatabase::class)->forClient($client, function () use ($from, $to) {
            $moved = [];
            foreach (ResolveTemplate::SCOPED as $model) {
                $column = $model::templateColumn();
                $count = $model::withoutGlobalScopes()->where($column, $from)->update([$column => $to]);
                if ($count) {
                    $moved[class_basename($model)] = $count;
                }
            }
            // Navigation and listings are cached per client; show the moved rows at once.
            Cache::flush();

            return $moved;
        });
    }

    /** How many rows a switch would move, for the warning shown before saving. */
    public static function countContent(Client $client, string $from): int
    {
        return (int) app(ClientDatabase::class)->forClientOr($client, function () use ($from) {
            $total = 0;
            foreach (ResolveTemplate::SCOPED as $model) {
                $total += $model::withoutGlobalScopes()->where($model::templateColumn(), $from)->count();
            }

            return $total;
        }, 0);
    }
}
