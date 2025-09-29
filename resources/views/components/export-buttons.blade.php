<form action="/export" method="GET" class="w-full">
    @csrf

    <div class="card bg-base-100 shadow-md">
        <div class="card-body">
            <h2 class="card-title">Esporta Excel</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                <div class="form-control">
                    <label class="label" for="start_date">
                        <span class="label-text">Data inizio</span>
                    </label>
                    <input
                        id="start_date"
                        name="start_date"
                        type="date"
                        class="input input-bordered w-full"
                        aria-label="Data inizio"
                    />
                </div>

                <div class="form-control">
                    <label class="label" for="end_date">
                        <span class="label-text">Data fine</span>
                    </label>
                    <input
                        id="end_date"
                        name="end_date"
                        type="date"
                        class="input input-bordered w-full"
                        aria-label="Data fine"
                    />
                </div>
            </div>
        </div>

        <div class="card-actions justify-end p-4">
            <button type="submit" class="btn btn-primary">Esporta</button>
        </div>
    </div>
</form>