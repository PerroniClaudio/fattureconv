<nav class="navbar bg-base-200 shadow-sm">
  <div class="flex-1">
    <a class="btn btn-ghost text-xl">{{ config('app.name') }}</a>
  </div>
  <div class="flex-none">
    <ul class="menu menu-horizontal px-1">
      @auth
        <li><a href="/app" class="">Fatture</a></li>
        <li><a href="/zip-exports">Esportazioni ZIP</a></li>
      @endauth
    </ul>
  </div>
</nav>