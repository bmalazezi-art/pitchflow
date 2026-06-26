<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · PitchFlow</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f7fa;color:#172033;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.box{width:min(520px,100%);text-align:center}.mark{width:44px;height:44px;margin:0 auto 28px;display:grid;place-items:center;border-radius:8px;background:#2563eb;color:white;font-weight:800;font-size:20px}h1{font-size:72px;margin:0;color:#cbd5e1}h2{font-size:28px;margin:5px 0 10px}p{color:#667085;line-height:1.6}a{display:inline-flex;min-height:42px;margin-top:18px;padding:0 17px;align-items:center;border-radius:7px;background:#2563eb;color:white;text-decoration:none;font-weight:700}
    </style>
</head>
<body><main class="box"><div class="mark">P</div><h1>@yield('code')</h1><h2>@yield('title')</h2><p>@yield('message')</p><a href="{{ url('/') }}">{{ app()->getLocale() === 'sq' ? 'Kthehu në fillim' : 'Return home' }}</a></main></body>
</html>
