<!doctype html>
<html lang="it" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>FattureConv — Home</title>
    <link rel="icon" href="/favicon.ico" />
  @vite(['resources/css/app.css','resources/js/app.js'])
  </head>
  <body class="bg-base-300 text-base-content subpixel-antialiased">
    <div class="min-h-screen flex flex-col">
      @include('components.navbar')
      <main class="container mx-auto px-4 py-12 flex-1">
        <!-- Flash messages -->
        @if(session('status_message') || session('error_message') || session('info_message'))
          <div class="mb-6">
            @if(session('status_message'))
              <div role="status" class="alert alert-success">
                
                  <x-lucide-check-circle class="h-6 w-6" />
                  <span>{{ session('status_message') }}</span>
                
              </div>
            @endif
            @if(session('error_message'))
              <div role="alert" class="alert alert-error">
                
                  <x-lucide-x-circle class="h-6 w-6" />
                  <span>{{ session('error_message') }}</span>
                
              </div>
            @endif
            @if(session('info_message'))
              <div role="status" class="alert alert-info">
             
                  <x-lucide-info class="h-6 w-6" />
                  <span>{{ session('info_message') }}</span>
                
              </div>
            @endif
          </div>
        @endif

        {{ $slot }}
      </main>
      @include('components.footer')
    </div>
  
  </body>
</html>
