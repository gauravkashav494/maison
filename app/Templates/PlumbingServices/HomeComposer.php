<?php

namespace App\Templates\PlumbingServices;

use App\Models\Faq;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Testimonial;
use Illuminate\View\View;

/**
 * Supplies the Plumbing Services homepage with its section data (popular services,
 * emergency service, areas, testimonials, projects, FAQs, posts) and the ordered list
 * of enabled sections.
 */
class HomeComposer
{
    public function compose(View $view): void
    {
        $home = tsetting('home');
        $limit = fn (string $key, int $default) => max(3, (int) ($home[$key] ?? $default));

        $sections = collect($home['sections'] ?? [])
            ->filter(fn ($s) => ! empty($s['enabled']) && ! empty($s['key']))
            ->pluck('key')->values();

        $view->with([
            'g' => $home,
            'sections' => $sections,
            'popular' => Service::active()->orderByDesc('is_popular')->limit($limit('popular_limit', 8))->get(),
            'emergencyService' => Service::active()->where('is_emergency', true)->first(),
            'areas' => ServiceArea::active()->get(),
            'testimonials' => Testimonial::with('service')->active()->limit($limit('reviews_limit', 8))->get(),
            'projects' => Project::with('service')->active()->limit($limit('projects_limit', 6))->get(),
            'faqs' => Faq::active()->limit($limit('faq_limit', 8))->get(),
            'posts' => Post::published()->limit(4)->get(),
        ]);
    }
}
