<section class="p-4">

    <div class="flex justify-end">
        <div class="w-full max-w-sm">
            <input id="archive-search" type="search" placeholder="Cerca..." class="input input-bordered w-full"
                autocomplete="off" />
        </div>
    </div>

    <ul class="list rounded-box mt-4 space-y-2" id="archive-list">

    </ul>

</section>

<template id="archive-list-item-template">
    <li class="list-row flex items-center justify-between w-full gap-4" data-file-id="">
        <div class="flex-1 truncate">
            <span data-file-name class="font-medium truncate"></span>
            <span data-file-date class="block text-sm text-muted"></span>
        </div>
        <div class="dropdown dropdown-end">
            <button type="button" class="btn btn-sm btn-ghost btn-square" tabindex="0" aria-label="Azioni">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"
                    aria-hidden="true">
                    <circle cx="12" cy="6" r="1.25" />
                    <circle cx="12" cy="12" r="1.25" />
                    <circle cx="12" cy="18" r="1.25" />
                </svg>
            </button>
            <ul class="menu menu-sm dropdown-content bg-base-200 rounded-box z-10 mt-2 w-56 p-2 shadow" tabindex="0">
                <li><button type="button" data-download-original>Scarica file originale</button></li>
                <li><button type="button" data-download-word>Scarica Word</button></li>
                <li><button type="button" data-download-pdf>Scarica PDF</button></li>
                <li><button type="button" data-month-reference>Modifica mese riferimento</button></li>
            </ul>
        </div>
    </li>
</template>

<dialog id="archive-merge-modal" class="modal">
    <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Unione PDF</h3>
        <p class="mt-1 text-sm text-muted" data-merge-file>&mdash;</p>

        <div class="mt-4 space-y-3">
            <div class="flex items-center gap-2 text-sm" data-merge-status-wrapper>
                <span class="loading loading-spinner loading-sm hidden" data-merge-spinner></span>
                <span data-merge-status>Seleziona un file per avviare l'unione.</span>
            </div>
            <div class="alert alert-error hidden text-sm flex flex-col gap-4" data-merge-error>
                <span data-merge-error-text class="flex-1"></span>
                <button type="button" class="btn btn-xs btn-outline" data-merge-error-copy>Copia errore</button>
            </div>
            <p class="text-xs text-muted" data-merge-hint>
                L'unione genera un PDF combinando il documento originale con quello elaborato.
            </p>
        </div>

        <div class="modal-action mt-6">
            <button type="button" class="btn btn-primary" data-merge-start>Avvia unione</button>
            <button type="button" class="btn btn-success hidden" data-merge-download>Scarica PDF</button>
            <button type="button" class="btn" data-merge-close>Chiudi</button>
        </div>
    </div>
</dialog>
