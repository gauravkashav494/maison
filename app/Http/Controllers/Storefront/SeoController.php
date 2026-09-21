<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceArea;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => now()]];
        $urls[] = ['loc' => route('journal.index'), 'priority' => '0.6', 'changefreq' => 'weekly'];

        if (template()->supportsServices()) {
            // Service-business templates: services and service areas instead of the catalogue.
            $urls[] = ['loc' => route('services.index'), 'priority' => '0.9', 'changefreq' => 'weekly'];
            $urls[] = ['loc' => route('services.emergency'), 'priority' => '0.8', 'changefreq' => 'monthly'];
            $urls[] = ['loc' => route('areas.index'), 'priority' => '0.7', 'changefreq' => 'monthly'];
            $urls[] = ['loc' => route('projects.index'), 'priority' => '0.5', 'changefreq' => 'monthly'];
            foreach (Service::active()->where('noindex', false)->get() as $s) {
                $urls[] = ['loc' => $s->url, 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $s->updated_at];
            }
            foreach (ServiceArea::active()->where('noindex', false)->get() as $a) {
                $urls[] = ['loc' => $a->url, 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $a->updated_at];
            }
        } else {
            $urls[] = ['loc' => route('shop.index'), 'priority' => '0.9', 'changefreq' => 'daily'];
            $urls[] = ['loc' => route('collections.index'), 'priority' => '0.8', 'changefreq' => 'weekly'];
            foreach (Category::active()->where('noindex', false)->get() as $c) {
                $urls[] = ['loc' => $c->url, 'priority' => '0.8', 'changefreq' => 'weekly', 'lastmod' => $c->updated_at];
            }
            foreach (Collection::active()->where('noindex', false)->get() as $c) {
                $urls[] = ['loc' => $c->url, 'priority' => '0.8', 'changefreq' => 'weekly', 'lastmod' => $c->updated_at];
            }
            foreach (Product::active()->where('noindex', false)->get() as $p) {
                $urls[] = ['loc' => $p->url, 'priority' => '0.7', 'changefreq' => 'weekly', 'lastmod' => $p->updated_at];
            }
        }
        foreach (Page::active()->where('noindex', false)->get() as $p) {
            $urls[] = ['loc' => $p->url, 'priority' => '0.4', 'changefreq' => 'monthly', 'lastmod' => $p->updated_at];
        }
        foreach (Post::published()->where('noindex', false)->get() as $p) {
            $urls[] = ['loc' => $p->url, 'priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $p->updated_at];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc>';
            if (! empty($u['lastmod'])) {
                $xml .= '<lastmod>'.$u['lastmod']->toAtomString().'</lastmod>';
            }
            $xml .= '<changefreq>'.$u['changefreq'].'</changefreq><priority>'.$u['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        if (setting('seo.discourage_indexing', false)) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /search',
            'Disallow: /api/',
        ];
        if ($extra = setting('seo.robots_extra')) {
            $lines[] = trim($extra);
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
