<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'VeloraPlus Authentication' }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f5f7fb; color: #111827; }
        main { width: min(440px, calc(100% - 32px)); margin: 64px auto; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; box-shadow: 0 8px 30px rgba(15, 23, 42, .06); }
        h1 { margin-top: 0; font-size: 24px; }
        label { display: block; margin: 16px 0 6px; font-weight: 600; }
        input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
        button { margin-top: 20px; width: 100%; padding: 11px 14px; border: 0; border-radius: 8px; background: #111827; color: #fff; cursor: pointer; }
        a { color: #1d4ed8; }
        .links { display: flex; gap: 12px; justify-content: space-between; margin-top: 18px; font-size: 14px; }
        .notice { padding: 10px 12px; border-radius: 8px; background: #ecfdf5; color: #065f46; margin-bottom: 16px; }
        .errors { padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #991b1b; margin-bottom: 16px; }
        .errors ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <div class="notice">VeloraPlus Platform</div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</main>
</body>
</html>
