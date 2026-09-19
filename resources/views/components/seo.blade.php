@props(['seo'])

<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
<meta name="robots" content="{{ $seo->robots }}">
<link rel="canonical" href="{{ $seo->canonical }}">

<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:type" content="{{ $seo->ogType }}">
<meta property="og:site_name" content="{{ $seo->siteName }}">

@if ($seo->ogImage)
    <meta property="og:image" content="{{ $seo->ogImage }}">
@endif

@if ($seo->locale)
    <meta property="og:locale" content="{{ $seo->locale }}">
@endif

<meta name="twitter:card" content="{{ $seo->ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->title }}">
<meta name="twitter:description" content="{{ $seo->description }}">

@if ($seo->ogImage)
    <meta name="twitter:image" content="{{ $seo->ogImage }}">
@endif

@foreach ($seo->schema as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
@endforeach
