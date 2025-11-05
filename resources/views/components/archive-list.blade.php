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
        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-soft" data-download-word>
                <x-fileicon-microsoft-word class="h-4 w-4 text-soft-content" />
                <span class="ml-1">Scarica Word</span>
            </button>
            <button type="button" class="btn btn-sm btn-soft" data-download-pdf>
                <x-fileicon-adobe-acrobat class="h-4 w-4 text-soft-content" />
                <span class="ml-1">Scarica PDF</span>
            </button>
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
