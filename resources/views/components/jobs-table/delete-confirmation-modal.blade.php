{{-- Modal di conferma eliminazione --}}
<dialog id="deleteConfirmationModal" class="modal">
    <div class="modal-box">
        <h3 class="text-lg font-bold">
            <x-lucide-alert-triangle class="inline-block w-6 h-6 mr-2 text-warning" />
            Conferma eliminazione
        </h3>
        <p class="py-4">
            Sei sicuro di voler eliminare questo file?
            <br><br>
            <strong id="deleteFileName" class="text-base-content"></strong>
            <br><br>
            <span class="text-sm text-warning">Questa azione non può essere annullata.</span>
        </p>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn btn-ghost mr-2">Annulla</button>
            </form>
            <button id="confirmDeleteBtn" class="btn btn-error">
                <x-lucide-trash-2 class="w-4 h-4 mr-1" />
                Elimina
            </button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
