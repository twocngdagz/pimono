<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PiMono SPA</title>
    {{-- In test environment the Vite build (manifest) is not generated; skip asset injection to avoid exceptions. --}}
    @if (!app()->environment('testing'))
        @vite(['resources/css/app.css','resources/js/app.js'])
    @endif
</head>
<body class="antialiased bg-gray-100 text-gray-900">
    <div id="app"></div>
</body>
</html>
