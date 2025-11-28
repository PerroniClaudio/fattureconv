<dialog id="month_reference_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Modifica Mese di Riferimento</h3>
        <p class="py-4">Seleziona il nuovo mese di riferimento per questo file.</p>

        <form id="month_reference_form" method="dialog">
            <input type="hidden" id="month_reference_file_id" name="file_id">

            <div class="form-control w-full max-w-xs">
                <label class="label">
                    <span class="label-text">Mese di Riferimento</span>
                </label>
                <input type="month" id="month_reference_input" name="month_reference"
                    class="input input-bordered w-full max-w-xs" required />
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="month_reference_modal.close()">Annulla</button>
                <button type="submit" class="btn btn-primary">Salva</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
