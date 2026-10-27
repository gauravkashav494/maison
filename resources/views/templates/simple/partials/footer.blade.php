{{-- Plain footer: a line about the business, the links and the policies. --}}
<footer class="ol-foot">
    <div class="ol-foot-inner">
        <p>{{ setting('site.footer_blurb', '') }}</p>

        <div class="ol-foot-links">
            @foreach($menus['footer'] ?? [] as $item)
                <a href="{{ $item->url }}">{{ $item->label }}</a>
            @endforeach
        </div>

        <div class="ol-foot-links">
            @if($email = setting('site.support_email'))<a href="mailto:{{ $email }}">{{ $email }}</a>@endif
            @if($phone = setting('site.support_phone'))<a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>@endif
        </div>

        <div class="ol-foot-links">
            <span>© {{ date('Y') }} {{ setting('site.name', config('app.name')) }}</span>
            @foreach($menus['legal'] ?? [] as $item)
                <a href="{{ $item->url }}">{{ $item->label }}</a>
            @endforeach
        </div>
    </div>
</footer>
