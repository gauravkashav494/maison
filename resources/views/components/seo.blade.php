@props(['seo' => null])
@php
    $seo ??= \App\Support\Seo::simple(config('app.name'));
    $title = $seo->fullTitle();
    $siteName = setting('site.name', config('app.name'));
@endphp
<title>{{ $title }}</title>
@if($seo->description)<meta name="description" content="{{ $seo->description }}">@endif
@if($seo->noindex)<meta name="robots" content="noindex, nofollow">@else<meta name="robots" content="index, follow, max-image-preview:large">@endif
@if($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title }}">
@if($seo->description)<meta property="og:description" content="{{ $seo->description }}">@endif
@if($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif
@if($seo->image)<meta property="og:image" content="{{ $seo->image }}">@endif
<meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $title }}">
@if($seo->description)<meta name="twitter:description" content="{{ $seo->description }}">@endif
@if($seo->image)<meta name="twitter:image" content="{{ $seo->image }}">@endif
@if($v = setting('seo.google_site_verification'))<meta name="google-site-verification" content="{{ $v }}">@endif
@foreach($seo->jsonLd as $ld)
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
@if($gtag = setting('seo.gtag_id'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gtag }}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $gtag }}');</script>
@endif
