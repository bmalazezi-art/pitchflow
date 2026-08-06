<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · PitchFlow</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(180deg,#f8fafc,#f0fdf4);color:#172033;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.box{width:min(560px,100%);padding:34px 28px;text-align:center;border:1px solid #bbf7d0;border-radius:18px;background:rgba(255,255,255,.94);box-shadow:0 22px 70px rgba(15,23,42,.1)}.mark{width:48px;height:48px;margin:0 auto 22px;display:grid;place-items:center;border-radius:12px;background:#22c55e;color:white;font-weight:900;font-size:22px}h1{font-size:72px;line-height:1;margin:0;color:#bbf7d0}h2{font-size:28px;margin:8px 0 10px}p{max-width:440px;margin:0 auto;color:#667085;line-height:1.6}.actions{margin-top:22px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap}a,button{min-height:44px;padding:0 17px;display:inline-flex;align-items:center;justify-content:center;border-radius:9px;font:inherit;font-weight:800;text-decoration:none;cursor:pointer}a{border:1px solid #16a34a;background:#16a34a;color:white}button{border:1px solid #bbf7d0;color:#166534;background:#f0fdf4}@media(max-width:480px){body{padding:14px}.box{padding:28px 18px;border-radius:14px}h1{font-size:58px}.actions{display:grid}a,button{width:100%}}
    </style>
</head>
<body><main class="box"><div class="mark">P</div><h1>@yield('code')</h1><h2>@yield('title')</h2><p>@yield('message')</p><div class="actions"><button type="button" onclick="history.length > 1 ? history.back() : location.href='{{ route('dashboard') }}'">{{ app()->getLocale() === 'sq' ? 'Kthehu prapa' : 'Go back' }}</button><a href="{{ route('dashboard') }}">{{ app()->getLocale() === 'sq' ? 'Shko te paneli' : 'Go to dashboard' }}</a></div></main></body>
</html>
