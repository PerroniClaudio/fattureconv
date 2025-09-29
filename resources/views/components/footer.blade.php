<footer class="footer p-4 bg-slate-800 text-base-content">
	<div class="container mx-auto flex items-center justify-between">
		<div class="flex items-center">
			<a href="{{ url('/app') }}" aria-label="{{ config('app.name') }}">
				<img src="/logo" alt="{{ config('app.name') }}" class="w-28 h-auto" />
			</a>
		</div>

		<div class="text-sm text-base-200">
			&copy; {{ date('Y') }} {{ config('app.name') }}
		</div>
	</div>
</footer>
