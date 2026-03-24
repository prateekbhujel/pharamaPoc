<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>pharamaPOC</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|noto-sans-devanagari:400,500,700&display=swap" rel="stylesheet" />

    @viteReactRefresh
    @vite('resources/js/main.jsx')
</head>
<body class="min-h-screen bg-[#eef4ff] text-slate-900 antialiased">
    <div id="app"></div>
</body>
</html>
