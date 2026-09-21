<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ServiceRequest;
use App\Models\Testimonial;
use App\Support\Media;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Service-business pages for templates that opt in via Template::supportsServices():
 * services, problem shortcuts, emergency, service areas, projects, the booking and
 * quote flows, booking lookup and the live service search. Catalogue templates get 404.
 */
class ServicesController extends Controller
{
    // ---- Services ---------------------------------------------------------

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $services = $this->searchQuery($q)->get();

        return view('services.index', [
            'q' => $q,
            'services' => $services,
            'seo' => Seo::simple($q !== '' ? "Search: {$q}" : 'Plumbing services', 'Every plumbing service we offer — leak repair, pipes, drains, bathroom & kitchen plumbing, tanks, pumps and 24×7 emergencies.', $q !== ''),
        ]);
    }

    public function show(string $slug): View
    {
        $service = Service::active()->where('slug', $slug)->firstOrFail();
        $faqs = collect($service->faqs ?: [])->filter(fn ($f) => ! empty($f['question']) && ! empty($f['answer']))->values();
        if ($faqs->isEmpty()) {
            $faqs = Faq::active()->limit(5)->get()->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer]);
        }

        $jsonLd = [
            $this->breadcrumbs([['Services', route('services.index')], [$service->name, $service->url]]),
            array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => $service->name,
                'serviceType' => $service->name,
                'description' => $service->seoDescription(),
                'image' => $service->image_url,
                'url' => $service->url,
                'provider' => $this->localBusiness(false),
                'areaServed' => ServiceArea::active()->pluck('name')->map(fn ($n) => ['@type' => 'City', 'name' => $n])->all(),
            ]),
            $this->faqSchema($faqs),
        ];

        return view('services.show', [
            'service' => $service,
            'faqs' => $faqs,
            'related' => Service::active()->where('id', '!=', $service->id)->orderByDesc('is_popular')->limit(6)->get(),
            'testimonials' => Testimonial::with('service')->active()->where('service_id', $service->id)->limit(6)->get()
                ->whenEmpty(fn () => Testimonial::with('service')->active()->limit(6)->get()),
            'projects' => Project::with('service')->active()->where('service_id', $service->id)->limit(4)->get(),
            'areas' => ServiceArea::active()->get(),
            'rating' => Testimonial::active()->selectRaw('avg(rating) as avg, count(*) as n')->reorder()->first(),
            'seo' => Seo::forModel($service, $service->url, 'website', $jsonLd),
        ]);
    }

    /** Problem tiles on the homepage link here; they resolve to the mapped service or to the booking flow. */
    public function problem(string $slug): RedirectResponse
    {
        $problem = collect(tsetting('home.problems', []))->first(fn ($p) => str($p['label'] ?? '')->slug()->value() === $slug);
        $service = $problem && ! empty($problem['service']) ? Service::active()->where('slug', $problem['service'])->first() : null;

        return $service
            ? redirect()->to($service->url)
            : redirect()->route('booking.create', ['problem' => $problem['label'] ?? null]);
    }

    public function emergency(): View
    {
        $service = Service::active()->where('is_emergency', true)->first();

        return view('services.emergency', [
            'service' => $service,
            'steps' => [
                ['Turn off the main water valve', 'It is usually near the meter, the overhead tank inlet or under the kitchen sink.'],
                ['Switch off the geyser and nearby power points', 'Water and electricity are a dangerous mix — isolate them first.'],
                ['Contain the water', 'Place a bucket under the leak and move electronics and valuables away.'],
                ['Call us', 'Tell us what you see; we will guide you on the phone while the plumber is on the way.'],
            ],
            'seo' => Seo::simple('Emergency plumber — 24×7', tsetting('site.emergency_note')),
        ]);
    }

    // ---- Service areas -------------------------------------------------------

    public function areas(): View
    {
        return view('services.areas', [
            'areas' => ServiceArea::active()->get(),
            'seo' => Seo::simple('Service areas', 'Cities and localities where our plumbers are available for same-day and emergency visits.'),
        ]);
    }

    public function area(string $slug): View
    {
        $area = ServiceArea::active()->where('slug', $slug)->firstOrFail();

        return view('services.area', [
            'area' => $area,
            'services' => Service::active()->orderByDesc('is_popular')->limit(9)->get(),
            'testimonials' => Testimonial::with('service')->active()->where('location', 'like', "%{$area->name}%")->limit(6)->get()
                ->whenEmpty(fn () => Testimonial::with('service')->active()->limit(6)->get()),
            'others' => ServiceArea::active()->where('id', '!=', $area->id)->get(),
            'seo' => Seo::forModel($area, $area->url, 'website', [
                $this->breadcrumbs([['Service areas', route('areas.index')], [$area->name, $area->url]]),
                $this->localBusiness(true, $area),
            ]),
        ]);
    }

    public function projects(): View
    {
        return view('services.projects', [
            'projects' => Project::with('service')->active()->paginate(12),
            'seo' => Seo::simple('Recent plumbing work', 'Before and after photos of real jobs completed by our plumbers.'),
        ]);
    }

    // ---- Booking ----------------------------------------------------------

    public function book(Request $request): View
    {
        $services = Service::active()->get(['id', 'name', 'slug', 'icon', 'is_emergency']);
        $preselected = $request->query('service') ? $services->firstWhere('slug', $request->query('service')) : null;

        return view('services.book', [
            'services' => $services,
            'areas' => ServiceArea::active()->get(['id', 'name', 'slug']),
            'preselected' => $preselected,
            'problem' => (string) $request->query('problem', ''),
            'emergency' => $request->boolean('emergency'),
            'user' => $request->user(),
            'seo' => Seo::simple('Book a plumber', 'Tell us the problem, pick a time and a verified plumber will visit.', true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'service_name' => ['required_without:service_id', 'nullable', 'string', 'max:120'],
            'problem' => ['nullable', 'string', 'max:2000'],
            'address' => ['required', 'string', 'max:300'],
            'area' => ['nullable', 'string', 'max:120'],
            'service_area_id' => ['nullable', 'integer', Rule::exists('service_areas', 'id')],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', Rule::in(array_keys(ServiceRequest::SLOTS))],
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\s-]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'type' => ['nullable', Rule::in(['booking', 'emergency'])],
            'website' => ['prohibited'], // honeypot
        ]);
        unset($data['website']);

        $service = ! empty($data['service_id']) ? Service::find($data['service_id']) : null;
        $data['service_name'] = $service?->name ?? $data['service_name'];
        $data['type'] = $data['type'] ?? ($service?->is_emergency ? 'emergency' : 'booking');
        $data['template'] = template()->id();
        $data['user_id'] = $request->user()?->id;
        if (($data['time_slot'] ?? null) === 'asap') {
            $data['preferred_date'] = $data['preferred_date'] ?? today();
        }

        $booking = ServiceRequest::create($data);
        $request->session()->put('booking.'.$booking->reference, true);

        return redirect()->to($booking->url);
    }

    public function confirmation(Request $request, string $reference): View
    {
        $booking = ServiceRequest::with(['service'])->where('reference', $reference)->firstOrFail();
        $owned = $request->session()->has('booking.'.$reference)
            || ($request->user() && $booking->user_id === $request->user()->id);
        abort_unless($owned, 404);

        return view('services.confirmation', [
            'booking' => $booking,
            'seo' => Seo::simple('Request received', null, true),
        ]);
    }

    // ---- Quote ------------------------------------------------------------

    public function quote(Request $request): View
    {
        return view('services.quote', [
            'services' => Service::active()->get(['id', 'name', 'slug']),
            'areas' => ServiceArea::active()->get(['id', 'name', 'slug']),
            'user' => $request->user(),
            'seo' => Seo::simple('Request a quote', tsetting('site.quote_text'), true),
        ]);
    }

    public function storeQuote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'problem' => ['required', 'string', 'max:3000'],
            'address' => ['nullable', 'string', 'max:300'],
            'area' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\s-]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['prohibited'],
        ]);
        unset($data['website']);

        $data['service_name'] = ! empty($data['service_id']) ? Service::find($data['service_id'])?->name : 'General requirement';
        $data['type'] = 'quote';
        $data['time_slot'] = 'asap';
        $data['template'] = template()->id();
        $data['user_id'] = $request->user()?->id;

        $quote = ServiceRequest::create($data);
        $request->session()->put('booking.'.$quote->reference, true);

        return redirect()->to($quote->url);
    }

    // ---- My bookings -----------------------------------------------------

    /** Guests look a request up by reference + phone; signed-in customers see their list. */
    public function bookings(Request $request): View
    {
        $found = null;
        $bookings = collect();

        if ($request->user()) {
            $bookings = ServiceRequest::with('service')->where('user_id', $request->user()->id)->orderByDesc('id')->get();
        }

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'reference' => ['required', 'string', 'max:20'],
                'phone' => ['required', 'string', 'max:30'],
            ]);
            $digits = fn ($v) => substr(preg_replace('/\D+/', '', $v), -10);
            $found = ServiceRequest::with('service')->where('reference', strtoupper(trim($data['reference'])))->first();
            if (! $found || $digits($found->phone) !== $digits($data['phone'])) {
                $found = null;
                $request->session()->flash('lookup_error', 'We could not find a request with that reference and phone number.');
            } else {
                $request->session()->put('booking.'.$found->reference, true);
            }
        }

        return view('services.bookings', [
            'bookings' => $bookings,
            'found' => $found,
            'seo' => Seo::simple('My bookings', null, true),
        ]);
    }

    public function accountBookings(Request $request): View
    {
        return view('account.bookings', [
            'bookings' => ServiceRequest::with('service')->where('user_id', $request->user()->id)->orderByDesc('id')->get(),
            'seo' => Seo::simple('My bookings', null, true),
        ]);
    }

    // ---- Contact (phone-first variant of the shared form) -----------------

    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\s-]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'service' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:3000'],
            'website' => ['prohibited'],
        ]);
        unset($data['website']);
        $data['subject'] = $data['service'] ?: 'Enquiry';
        ContactMessage::create($data);

        return back()->with('contact_status', 'Thank you! We have received your message and will call you back shortly.');
    }

    // ---- Search -----------------------------------------------------------

    public function searchJson(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $services = $this->searchQuery($q)->limit(8)->get()->map(fn (Service $s) => [
            'name' => $s->name,
            'excerpt' => $s->excerpt,
            'icon' => $s->icon,
            'url' => $s->url,
            'book_url' => $s->book_url,
            'is_emergency' => $s->is_emergency,
        ]);

        $problems = collect(tsetting('home.problems', []))
            ->filter(fn ($p) => $q === '' || stripos($p['label'] ?? '', $q) !== false)
            ->take(6)->map(fn ($p) => ['label' => $p['label'], 'icon' => $p['icon'] ?? 'help', 'url' => route('services.problem', str($p['label'])->slug())])->values();

        return response()->json(['services' => $services, 'problems' => $problems]);
    }

    /** Every word must match the name, excerpt, description or listed problems. */
    private function searchQuery(string $q)
    {
        $words = array_values(array_filter(preg_split('/\s+/', $q)));

        return Service::active()->where(function ($outer) use ($words) {
            foreach ($words as $word) {
                $outer->where(fn ($w) => $w->where('name', 'like', "%{$word}%")
                    ->orWhere('excerpt', 'like', "%{$word}%")
                    ->orWhere('description', 'like', "%{$word}%")
                    ->orWhere('problems', 'like', "%{$word}%"));
            }
        })->orderByDesc('is_popular');
    }

    // ---- Structured data ---------------------------------------------------

    private function breadcrumbs(array $items): array
    {
        $list = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')]];
        foreach ($items as $i => [$name, $url]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => $url];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    private function faqSchema($faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])],
            ])->values()->all(),
        ];
    }

    public static function localBusiness(bool $withContext = true, ?ServiceArea $area = null): array
    {
        $site = tsetting('site', []);

        return array_filter([
            '@context' => $withContext ? 'https://schema.org' : null,
            '@type' => 'Plumber',
            'name' => $site['business_name'] ?? setting('site.name'),
            'url' => url('/'),
            'telephone' => $site['phone'] ?? null,
            'email' => $site['email'] ?? null,
            'image' => Media::url(setting('seo.default_og_image')),
            'address' => ! empty($site['address']) ? ['@type' => 'PostalAddress', 'streetAddress' => $site['address'], 'addressCountry' => 'IN'] : null,
            'openingHours' => $site['hours'] ?? null,
            'areaServed' => $area ? ['@type' => 'City', 'name' => $area->name] : null,
            'priceRange' => '₹₹',
            'sameAs' => array_values(array_filter(array_map(fn ($s) => ($s['url'] ?? '#') !== '#' ? $s['url'] : null, $site['social'] ?? []))),
        ]);
    }
}
