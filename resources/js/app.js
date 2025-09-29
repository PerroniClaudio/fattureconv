import './bootstrap';
import { initJobsTable } from './jobs-table';

// FilePond imports
import * as FilePond from 'filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';

// Register plugin(s)
FilePond.registerPlugin(FilePondPluginFileValidateType);

// Auto-initialize any file input with FilePond when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	// FilePond
	const input = document.querySelector('#pdf-file');
	if (input) {
			
			const pond = FilePond.create(input, {
				allowMultiple: true,
				acceptedFileTypes: ['application/pdf'],
				maxFiles: 20,
				server: {
					process: {
						url: '/upload/process',
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
						},
						withCredentials: false,
						onload: (response) => response,
					},
					revert: null,
				},
			});

			pond.setOptions({
				labelIdle: 'Trascina e rilascia i tuoi file oppure <span class="filepond--label-action"> Sfoglia <span>',
				labelInvalidField: "Il campo contiene dei file non validi",
				labelFileWaitingForSize: "In attesa della dimensione",
				labelFileSizeNotAvailable: "Dimensione non disponibile",
				labelFileLoading: "Caricamento",
				labelFileLoadError: "Errore durante il caricamento",
				labelFileProcessing: "Caricamento",
				labelFileProcessingComplete: "Caricamento completato",
				labelFileProcessingAborted: "Caricamento cancellato",
				labelFileProcessingError: "Errore durante il caricamento",
				labelFileProcessingRevertError: "Errore durante il ripristino",
				labelFileRemoveError: "Errore durante l'eliminazione",
				labelTapToCancel: "tocca per cancellare",
				labelTapToRetry: "tocca per riprovare",
				labelTapToUndo: "tocca per ripristinare",
				labelButtonRemoveItem: "Elimina",
				labelButtonAbortItemLoad: "Cancella",
				labelButtonRetryItemLoad: "Ritenta",
				labelButtonAbortItemProcessing: "Cancella",
				labelButtonUndoItemProcessing: "Indietro",
				labelButtonRetryItemProcessing: "Ritenta",
				labelButtonProcessItem: "Carica",
				labelMaxFileSizeExceeded: "La dimensione del file è eccessiva",
				labelMaxFileSize: "La dimensione massima del file è {filesize}",
				labelMaxTotalFileSizeExceeded: "Dimensione totale massima superata",
				labelMaxTotalFileSize: "La dimensione massima totale dei file è {filesize}",
				labelFileTypeNotAllowed: "File non supportato",
				fileValidateTypeLabelExpectedTypes: "Aspetta {allButLastType} o {lastType}",
				imageValidateSizeLabelFormatError: "Tipo di immagine non supportata",
				imageValidateSizeLabelImageSizeTooSmall: "L'immagine è troppo piccola",
				imageValidateSizeLabelImageSizeTooBig: "L'immagine è troppo grande",
				imageValidateSizeLabelExpectedMinSize: "La dimensione minima è {minWidth} × {minHeight}",
				imageValidateSizeLabelExpectedMaxSize: "La dimensione massima è {maxWidth} × {maxHeight}",
				imageValidateSizeLabelImageResolutionTooLow: "La risoluzione è troppo bassa",
				imageValidateSizeLabelImageResolutionTooHigh: "La risoluzione è troppo alta",
				imageValidateSizeLabelExpectedMinResolution: "La risoluzione minima è {minResolution}",
				imageValidateSizeLabelExpectedMaxResolution: "La risoluzione massima è {maxResolution}",
			});

			// Switch automatico alla tab "in corso" quando viene caricato un file
			pond.on('processfile', () => {
				const inProgressTab = document.querySelector('[data-tab="in-progress"]');
				if (inProgressTab) inProgressTab.click();
			});
	}
	// Jobs Table
	initJobsTable();
});
