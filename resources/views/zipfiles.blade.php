@component('components.layout')
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
	<!-- Sidebar: form di creazione export -->
	<aside class="col-span-1">
		<div class="card bg-base-100 shadow-md sticky top-4">
			<div class="card-body">
				<h2 class="card-title">Crea ZIP</h2>

				<form id="zipExportForm" class="grid grid-cols-1 gap-2 items-end">
					<div>
						<label class="label"><span class="label-text">Data inizio</span></label>
						<input type="date" id="start_date" name="start_date" class="input input-bordered w-full" required />
					</div>
					<div>
						<label class="label"><span class="label-text">Data fine</span></label>
						<input type="date" id="end_date" name="end_date" class="input input-bordered w-full" required />
					</div>
					<div>
						<button id="createExportBtn" class="btn btn-primary w-full">Crea export ZIP</button>
					</div>
				</form>
			</div>
		</div>
	</aside>

	<!-- Main content: tabella zip exports -->
	<main class="col-span-1 lg:col-span-3">
		<div class="card bg-base-100 shadow-md">
			<div class="card-body">
				<h2 class="card-title">Esporta ZIP</h2>
				<div class="mt-2">
					<x-zip-table />
				</div>
			</div>
		</div>
	</main>
</div>

@endcomponent