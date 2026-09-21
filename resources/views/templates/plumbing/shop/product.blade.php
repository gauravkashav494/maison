@extends('layouts.app')

@section('content')
@php
    $card = $product->toCard();
    $p = tsetting('site');
    $crumbs = [];
    if ($product->category?->parent) { $crumbs[$product->category->parent->name] = $product->category->parent->url; }
    if ($product->category) { $crumbs[$product->category->name] = $product->category->url; }
    $crumbs[$product->name] = null;
    $specs = collect($product->specifications ?? [])->filter(fn ($s) => ! empty($s['label']) && ! empty($s['value']))->values();
    // Fall back to the core fields so the table is never empty
    if ($specs->isEmpty()) {
        $specs = collect(array_filter(['Brand' => $product->brand, 'Material' => $product->material, 'Sizes' => implode(', ', $product->sizes ?? []), 'SKU' => $product->sku]))->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values();
    }
    $applications = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $product->applications))));
    $threshold = (int) setting('site.free_shipping_threshold', 0);
    $tabs = array_filter([
        'details' => 'Product details',
        'specs' => $specs->isNotEmpty() ? 'Specifications' : null,
        'applications' => $applications ? 'Applications' : null,
        'installation' => $product->installation_notes ? 'Installation & usage' : null,
        'shipping' => 'Shipping & returns',
        'reviews' => 'Reviews'.($product->review_count ? ' ('.number_format($product->review_count).')' : ''),
    ]);
    $wa = $p['whatsapp_number'] ?? null;
@endphp
<div x-data="productPage(@js($card))">
    <div class="p-container hidden pt-4 lg:block"><x-breadcrumbs :items="$crumbs" /></div>

    <section class="p-container grid gap-6 py-4 lg:grid-cols-12 lg:gap-10 lg:py-6">
        {{-- Gallery --}}
        <div class="lg:col-span-5">
            <div class="lg:sticky lg:top-[8.75rem]">
                <div x-ref="stage" class="card relative aspect-square overflow-hidden">
                    <template x-for="(s, i) in slides" :key="i">
                        <div x-show="image === i" class="absolute inset-0 bg-white">
                            <template x-if="s.type === 'image'"><img :src="s.src" :alt="p.name" class="h-full w-full cursor-zoom-in object-cover" @click="lightbox = true"></template>
                            <template x-if="s.type === 'video' && !isYouTube(s.src)"><video :src="s.src" controls playsinline class="h-full w-full object-cover"></video></template>
                            <template x-if="s.type === 'video' && isYouTube(s.src)"><iframe :src="embed(s.src)" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="Product video"></iframe></template>
                        </div>
                    </template>
                    <div class="absolute left-3 top-3 flex flex-col gap-1">
                        @if($product->discount_percent > 0)<span class="badge badge-off">{{ $product->discount_percent }}% off</span>@endif
                        @if($product->is_best_seller)<span class="badge badge-best">Bestseller</span>@elseif($product->is_new)<span class="badge badge-new">New</span>@endif
                    </div>
                    <div class="absolute right-3 top-3 flex gap-1.5">
                        <button type="button" @click="share()" class="grid h-9 w-9 place-items-center rounded-full bg-white/95 text-slate shadow-sm hover:text-primary" aria-label="Share"><x-ico name="share" :size="16" /></button>
                        <button type="button" @click="$store.wishlist.toggle(p.id)" class="grid h-9 w-9 place-items-center rounded-full bg-white/95 text-slate shadow-sm hover:text-danger" :class="$store.wishlist.has(p.id) && 'text-danger'" aria-label="Save to wishlist"><x-ico name="heart" :size="16" /></button>
                    </div>
                    <template x-if="slides.length > 1"><div><button type="button" @click="prev()" class="rail-btn absolute left-3 top-1/2 -translate-y-1/2" aria-label="Previous image"><x-ico name="chevron-left" :size="18" /></button><button type="button" @click="next()" class="rail-btn absolute right-3 top-1/2 -translate-y-1/2" aria-label="Next image"><x-ico name="chevron-right" :size="18" /></button></div></template>
                </div>
                <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto" x-show="slides.length > 1">
                    <template x-for="(s, i) in slides" :key="'t' + i">
                        <button type="button" @click="image = i" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 bg-white" :class="image === i ? 'border-primary' : 'border-line'" :aria-label="`Image ${i + 1}`">
                            <template x-if="s.type === 'image'"><img :src="s.src" alt="" class="img-cover"></template>
                            <template x-if="s.type === 'video'"><span class="grid h-full w-full place-items-center bg-deep text-white"><x-ico name="play" :size="16" /></span></template>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Buy box --}}
        <div class="lg:col-span-7">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate">
                @if($product->brand)<a href="{{ route('shop.index', ['brand' => $product->brand]) }}" class="font-bold uppercase tracking-wider text-primary">{{ $product->brand }}</a>@endif
                @if($product->sku)<span>SKU: <span class="font-semibold text-ink">{{ $product->sku }}</span></span>@endif
                @if($product->category)<span>· <a href="{{ $product->category->url }}" class="hover:text-primary">{{ $product->category->name }}</a></span>@endif
            </div>
            <h1 class="mt-1.5 font-display text-xl font-bold leading-snug lg:text-[1.75rem]">{{ $product->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                @if($product->review_count)<a href="#reviews" class="flex items-center gap-1.5"><x-rating :value="$product->rating" :size="15" /><span class="font-bold">{{ number_format($product->rating, 1) }}</span><span class="text-slate">({{ number_format($product->review_count) }} ratings)</span></a>@else<span class="text-slate">No reviews yet</span>@endif
                @if($product->in_stock)<span class="pill-ok"><x-ico name="check" :size="12" :stroke="3" /> In stock</span>@else<span class="badge badge-out">Out of stock</span>@endif
            </div>

            {{-- Price block --}}
            <div class="card mt-4 p-4 lg:p-5">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="price text-3xl text-ink">{{ money($product->price) }}</span>
                    @if($product->compare_at_price)<span class="mrp text-base">MRP {{ money($product->compare_at_price) }}</span><span class="off text-base">{{ $product->discount_percent }}% off</span>@endif
                </div>
                @if($product->compare_at_price)<p class="mt-1 text-sm font-semibold text-success">You save {{ money($product->compare_at_price - $product->price) }}</p>@endif
                <p class="mt-1 text-xs text-slate">{{ $p['gst_note'] ?? 'Inclusive of all taxes.' }}</p>

                @if(count($product->sizes ?? []) > 1)
                    <div class="mt-4">
                        <p class="label">Select size <span class="ml-1 font-normal text-slate" x-text="size"></span></p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($product->sizes as $s)<button type="button" @click="size = @js($s)" class="chip" :class="size === @js($s) && 'chip-active'">{{ $s }}</button>@endforeach
                        </div>
                    </div>
                @elseif(count($product->sizes ?? []) === 1)
                    <p class="mt-3 text-sm text-slate">Size / pack: <span class="font-semibold text-ink">{{ $product->sizes[0] }}</span></p>
                @endif

                <div x-ref="buy" class="mt-5 flex flex-wrap items-center gap-3">
                    <div class="stepper">
                        <button type="button" @click="quantity = Math.max(1, quantity - 1)" aria-label="Decrease quantity"><x-ico name="minus" :size="16" /></button>
                        <span x-text="quantity"></span>
                        <button type="button" @click="quantity = Math.min({{ $product->max_qty ?: 999 }}, quantity + 1)" aria-label="Increase quantity"><x-ico name="plus" :size="16" /></button>
                    </div>
                    @if($product->in_stock)
                        <button type="button" @click="add()" :disabled="$store.cart.busy" class="btn btn-primary btn-lg flex-1"><x-ico name="cart" :size="18" /> Add to cart</button>
                        <button type="button" @click="buyNow()" class="btn btn-accent btn-lg flex-1">Buy now</button>
                    @else
                        <button type="button" disabled class="btn btn-ghost btn-lg flex-1">Notify me when available</button>
                    @endif
                </div>
                <p x-show="qty" x-cloak class="mt-2 text-xs font-semibold text-success"><span x-text="qty"></span> already in your cart</p>
                @if($product->max_qty)<p class="mt-1 text-xs text-slate">Max {{ $product->max_qty }} per order</p>@endif

                <ul class="mt-4 grid gap-2 border-t border-line-soft pt-4 text-xs text-slate sm:grid-cols-2">
                    <li class="flex items-center gap-2"><x-ico name="truck" :size="15" class="text-primary" /> {{ $p['delivery_promise'] ?? 'Pan-India delivery' }}</li>
                    @if($threshold > 0)<li class="flex items-center gap-2"><x-ico name="tag" :size="15" class="text-primary" /> Free delivery over {{ money($threshold) }}</li>@endif
                    <li class="flex items-center gap-2"><x-ico name="wallet" :size="15" class="text-primary" /> UPI, cards, net banking & COD</li>
                    <li class="flex items-center gap-2"><x-ico name="rotate" :size="15" class="text-primary" /> 7-day returns on unused items</li>
                    <li class="flex items-center gap-2"><x-ico name="receipt" :size="15" class="text-primary" /> GST invoice available</li>
                    <li class="flex items-center gap-2"><x-ico name="badge" :size="15" class="text-primary" /> Genuine, manufacturer warranty</li>
                </ul>
            </div>

            {{-- Quick facts --}}
            @if($specs->isNotEmpty())
                <dl class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach($specs->take(6) as $s)<div class="rounded-lg bg-white px-3 py-2 ring-1 ring-line"><dt class="text-[0.6875rem] font-semibold uppercase tracking-wider text-slate">{{ $s['label'] }}</dt><dd class="mt-0.5 truncate text-sm font-semibold">{{ $s['value'] }}</dd></div>@endforeach
                </dl>
            @endif

            {{-- Bulk / help --}}
            <div class="mt-4 flex flex-col gap-3 rounded-xl bg-sky p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm font-bold text-deep">Buying in bulk?</p><p class="text-xs text-slate">Volume pricing for contractors, plumbers & builders.</p></div>
                <div class="flex gap-2">
                    <a href="{{ $p['bulk_cta_url'] ?? '/contact?subject=Bulk+quote' }}" class="btn btn-deep btn-sm">Request quote</a>
                    @if($wa)<a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}?text={{ rawurlencode('Hi, I have a question about '.$product->name.' ('.url()->current().')') }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm"><x-ico name="whatsapp" :size="14" /> Ask on WhatsApp</a>@endif
                </div>
            </div>
        </div>
    </section>

    {{-- Detail tabs --}}
    <section class="p-container pb-6">
        <div class="card overflow-hidden">
            <div class="no-scrollbar flex overflow-x-auto border-b border-line px-2">
                @foreach($tabs as $key => $label)<button type="button" @click="tab = @js($key)" class="tab-btn" :class="tab === @js($key) && 'is-active'">{{ $label }}</button>@endforeach
            </div>
            <div class="p-5 lg:p-6">
                <div x-show="tab === 'details'" class="prose-p max-w-3xl">
                    @if($product->description)<p class="text-base text-ink">{{ $product->description }}</p>@endif
                    @if($product->details){!! $product->details !!}@endif
                    @if(!$product->description && !$product->details)<p class="text-slate">No description has been added yet.</p>@endif
                </div>
                @if($specs->isNotEmpty())
                    <div x-show="tab === 'specs'" x-cloak class="max-w-3xl">
                        <table class="spec-table"><tbody>@foreach($specs as $s)<tr><th>{{ $s['label'] }}</th><td>{{ $s['value'] }}</td></tr>@endforeach</tbody></table>
                    </div>
                @endif
                @if($applications)
                    <div x-show="tab === 'applications'" x-cloak class="max-w-3xl">
                        <ul class="grid gap-2 sm:grid-cols-2">@foreach($applications as $a)<li class="flex items-start gap-2.5 rounded-lg bg-canvas px-3 py-2.5 text-sm"><span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-sky text-primary"><x-ico name="check" :size="12" :stroke="3" /></span>{{ $a }}</li>@endforeach</ul>
                    </div>
                @endif
                @if($product->installation_notes)
                    <div x-show="tab === 'installation'" x-cloak class="prose-p max-w-3xl"><p>{{ $product->installation_notes }}</p><p class="text-xs text-mist">Guidance only — follow the manufacturer’s instructions and applicable plumbing codes. Our support team can help with sizing and installation questions.</p></div>
                @endif
                <div x-show="tab === 'shipping'" x-cloak class="prose-p max-w-3xl">
                    <h3>Delivery</h3><p>{{ $p['delivery_promise'] ?? 'Dispatch within 24 hours on working days.' }} Fittings, taps and tools typically arrive in 2–4 days; pipes, tanks, pumps and sanitaryware in 4–6 days by surface transport.@if($threshold > 0) Free delivery on orders over {{ money($threshold) }}.@endif</p>
                    <h3>Returns</h3><p>Unused products in original packing can be returned within 7 days of delivery. Cut pipes, opened adhesives and installed sanitaryware are not returnable. Damaged or wrong items are replaced free of cost when reported within 48 hours.</p>
                    <h3>Payment & GST</h3><p>UPI, cards, net banking, wallets and cash on delivery. {{ $p['gst_note'] ?? 'Prices include GST.' }}</p>
                </div>
                <div x-show="tab === 'reviews'" x-cloak id="reviews">
                    <div class="grid gap-8 lg:grid-cols-12">
                        <div class="lg:col-span-4">
                            <div class="flex items-end gap-3"><p class="font-display text-5xl font-extrabold leading-none">{{ $product->review_count ? number_format($product->rating, 1) : 'New' }}</p><div><x-rating :value="$product->rating" :size="16" /><p class="mt-1 text-xs text-slate">{{ number_format($product->review_count) }} {{ Str::plural('rating', $product->review_count) }}</p></div></div>
                            @if($breakdown->sum())<ul class="mt-4 space-y-1.5">@for($i = 5; $i >= 1; $i--)@php $n = $breakdown[$i] ?? 0; $pct = round($n / $breakdown->sum() * 100); @endphp<li class="flex items-center gap-2 text-xs"><span class="w-5 font-semibold">{{ $i }}★</span><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full bg-accent" style="width: {{ $pct }}%"></span></span><span class="w-6 text-right text-slate">{{ $n }}</span></li>@endfor</ul>@endif
                            <div x-data="reviewForm()" class="mt-5">
                                <button type="button" @click="open = !open" class="btn btn-outline btn-block">Write a review</button>
                                @if(session('review_status'))<p class="mt-3 rounded-lg bg-success-light p-3 text-sm text-success">{{ session('review_status') }}</p>@endif
                                <form x-show="open" x-collapse x-cloak method="post" action="{{ route('products.reviews.store', $product->slug) }}" class="mt-4 space-y-3">
                                    @csrf
                                    <div><span class="label">Your rating</span><div class="flex gap-1">@for($i = 1; $i <= 5; $i++)<button type="button" @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" class="text-star" aria-label="{{ $i }} stars"><svg width="26" height="26" viewBox="0 0 24 24" :fill="(hover || rating) >= {{ $i }} ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.3L12 17.5l-5.7 3 1.1-6.3L2.8 9.7l6.4-.9L12 3z"/></svg></button>@endfor<input type="hidden" name="rating" :value="rating"></div></div>
                                    <label class="block"><span class="label">Name</span><input name="name" required value="{{ old('name', auth()->user()?->name) }}" class="field"></label>
                                    <label class="block"><span class="label">Email (not published)</span><input name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="field"></label>
                                    <label class="block"><span class="label">Title</span><input name="title" value="{{ old('title') }}" class="field" placeholder="Sum it up in a few words"></label>
                                    <label class="block"><span class="label">Review</span><textarea name="body" required rows="4" class="field" placeholder="How did it perform? Easy to install?">{{ old('body') }}</textarea></label>
                                    @if($errors->any())<p class="error-text">{{ $errors->first() }}</p>@endif
                                    <button type="submit" class="btn btn-primary">Submit review</button>
                                </form>
                            </div>
                        </div>
                        <div class="lg:col-span-8">
                            @if($reviews->isEmpty())
                                <p class="text-sm text-slate">No reviews yet — be the first to review this product.</p>
                            @else
                                <ul class="divide-y divide-line-soft">
                                    @foreach($reviews as $r)
                                        <li class="py-4 first:pt-0">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-full bg-sky text-xs font-bold text-primary">{{ Str::upper(Str::substr($r->name, 0, 1)) }}</span><span class="text-sm font-bold">{{ $r->name }}</span><span class="pill-ok">Verified</span></div>
                                                <span class="text-xs text-mist">{{ $r->created_at->format('d M Y') }}</span>
                                            </div>
                                            <div class="mt-2 flex items-center gap-2"><x-rating :value="$r->rating" :size="13" />@if($r->title)<span class="text-sm font-semibold">{{ $r->title }}</span>@endif</div>
                                            <p class="mt-1.5 text-sm leading-relaxed text-slate">{{ $r->body }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Related products --}}
    @if($related->isNotEmpty())
        <section class="p-container section pt-2">
            <x-section-head title="Related products" :href="$product->category?->url" :link="'More in '.($product->category?->name ?? 'category')" />
            <x-rail :products="$related" />
        </section>
    @endif
    @if($completeTheLook->isNotEmpty())
        <section class="p-container section pt-0">
            <x-section-head title="Frequently bought together" />
            <x-rail :products="$completeTheLook" />
        </section>
    @endif

    <div x-data="productRail('recent')" data-exclude="{{ $product->slug }}" x-show="items.length" x-cloak class="p-container section pt-0">
        <x-section-head title="Recently viewed" />
        <div x-data="rail()" class="relative"><div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0"><template x-for="p in items" :key="p.id"><div class="w-[11.5rem] shrink-0 sm:w-[13.5rem] lg:w-[15rem]" data-slide>@include('partials.card-dynamic')</div></template></div></div>
    </div>

    {{-- Sticky mobile buy bar --}}
    <div x-show="showSticky" x-cloak x-transition class="above-tabs border-t border-line bg-white p-3 shadow-float lg:hidden">
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $product->name }}</p><p class="price text-base">{{ money($product->price) }} <span x-text="size" class="text-xs font-normal text-slate"></span></p></div>
            @if($product->in_stock)<button type="button" @click="add()" class="btn btn-primary"><x-ico name="cart" :size="16" /> Add</button><button type="button" @click="buyNow()" class="btn btn-accent">Buy now</button>@endif
        </div>
    </div>

    {{-- Lightbox --}}
    <div x-show="lightbox" x-cloak x-transition.opacity class="fixed inset-0 z-[95] flex items-center justify-center bg-deep/95 p-4" @click.self="lightbox = false" @keydown.escape.window="lightbox = false">
        <button type="button" @click="lightbox = false" class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white" aria-label="Close"><x-ico name="close" :size="22" /></button>
        <template x-if="slides[image]?.type === 'image'"><img :src="slides[image].src" alt="" class="max-h-full max-w-full rounded-xl object-contain"></template>
        <button type="button" @click="prev()" class="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white" aria-label="Previous"><x-ico name="chevron-left" :size="22" /></button>
        <button type="button" @click="next()" class="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white" aria-label="Next"><x-ico name="chevron-right" :size="22" /></button>
    </div>
</div>
@endsection
