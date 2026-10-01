<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EGYCON — Cosplayer Register</title>
    <meta name="description" content="Register your cosplay character for EGYCON, Egypt's premier anime convention." />
    @viteReactRefresh
    @vite(['resources/js/app.jsx', 'resources/css/app.css'])
    @inertiaHead
  </head>
  <body>
    @inertia
  </body>
</html>
