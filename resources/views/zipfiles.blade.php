@component('components.layout')
<div class="card bg-base-100 shadow-md">
	<div class="card-body">
		<h2 class="card-title">Esporta ZIP</h2>

		<form id="zipExportForm" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
			<div>
				<label class="label"><span class="label-text">Data inizio</span></label>
				<input type="date" id="start_date" name="start_date" class="input input-bordered w-full" required />
			</div>
			<div>
				<label class="label"><span class="label-text">Data fine</span></label>
				<input type="date" id="end_date" name="end_date" class="input input-bordered w-full" required />
			</div>
			<div>
				<button id="createExportBtn" class="btn btn-primary">Crea export ZIP</button>
			</div>
		</form>

		<div class="mt-6">
			<x-zip-table />
		</div>
	</div>
</div>

@endcomponent