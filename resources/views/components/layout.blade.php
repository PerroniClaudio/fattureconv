<!doctype html>
<html lang="it" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>FattureConv — Home</title>
    <link rel="icon" href="/favicon.ico" />
  @vite(['resources/css/app.css','resources/js/app.js'])
  </head>
  <body class="bg-base-100 text-base-content">
    <div class="min-h-screen flex flex-col">
      @include('components.navbar')
      <main class="container mx-auto px-4 py-12 flex-1">
        {{ $slot }}
      </main>
      @include('components.footer')
    </div>
  
  </body>
</html>
