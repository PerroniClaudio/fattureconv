import "./bootstrap";

// Register plugin(s)

// Auto-initialize any file input with FilePond when DOM is ready
document.addEventListener("DOMContentLoaded", async () => {
    // FilePond: load only when file input exists
    const input = document.querySelector("#pdf-file");
    if (input) {
        const [FilePondModule, FilePondPluginModule] = await Promise.all([
            import("filepond"),
            import("filepond-plugin-file-validate-type"),
        ]);

        const FilePond = FilePondModule.default || FilePondModule;
        const FilePondPluginFileValidateType =
            FilePondPluginModule.default || FilePondPluginModule;
        FilePond.registerPlugin(FilePondPluginFileValidateType);
        const pond = FilePond.create(input, {
            allowMultiple: true,
            acceptedFileTypes: ["application/pdf"],
            maxFiles: 20,
            server: {
                process: {
                    url: "/upload/process",
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                    },
                    withCredentials: false,
                    onload: (response) => response,
                },
                revert: null,
            },
        });

        pond.setOptions({
            labelIdle:
                'Trascina e rilascia i tuoi file oppure <span class="filepond--label-action"> Sfoglia <span>',
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
            labelMaxTotalFileSize:
                "La dimensione massima totale dei file è {filesize}",
            labelFileTypeNotAllowed: "File non supportato",
            fileValidateTypeLabelExpectedTypes:
                "Aspetta {allButLastType} o {lastType}",
            imageValidateSizeLabelFormatError:
                "Tipo di immagine non supportata",
            imageValidateSizeLabelImageSizeTooSmall:
                "L'immagine è troppo piccola",
            imageValidateSizeLabelImageSizeTooBig: "L'immagine è troppo grande",
            imageValidateSizeLabelExpectedMinSize:
                "La dimensione minima è {minWidth} × {minHeight}",
            imageValidateSizeLabelExpectedMaxSize:
                "La dimensione massima è {maxWidth} × {maxHeight}",
            imageValidateSizeLabelImageResolutionTooLow:
                "La risoluzione è troppo bassa",
            imageValidateSizeLabelImageResolutionTooHigh:
                "La risoluzione è troppo alta",
            imageValidateSizeLabelExpectedMinResolution:
                "La risoluzione minima è {minResolution}",
            imageValidateSizeLabelExpectedMaxResolution:
                "La risoluzione massima è {maxResolution}",
        });

        pond.on("processfile", () => {
            const inProgressTab = document.querySelector(
                '[data-tab="in-progress"]'
            );
            if (inProgressTab) inProgressTab.click();
        });

        // collect FilePond server response ids and write to hidden input
        const processedIdsContainer = document.getElementById("processed-ids");
        const processedIdsInput = document.getElementById("processed-ids");
        if (processedIdsInput) {
            const ids = [];
            pond.on("processfile", (error, file) => {
                if (!error) {
                    const serverId =
                        file.serverId || (file.serverId === 0 ? "0" : null);
                    if (serverId) {
                        ids.push(serverId);
                        processedIdsInput.value = ids.join(",");
                    }
                }
            });
        }
    }

    // Jobs Table: load only when element exists
    if (document.getElementById("in-progress-table")) {
        const jobsModule = await import("./jobs-table");
        if (typeof jobsModule.initJobsTable === "function")
            jobsModule.initJobsTable();
    }

    // Zip Exports UI: load only when form exists
    if (document.getElementById("zipExportForm")) {
        const zipModule = await import("./zip-exports");
        if (typeof zipModule.initZipExports === "function")
            zipModule.initZipExports();
    }
});

// Month Reference Modal Logic
window.openMonthReferenceModal = function (id, currentMonth) {
    const modal = document.getElementById("month_reference_modal");
    const form = document.getElementById("month_reference_form");
    const idInput = document.getElementById("month_reference_file_id");
    const dateInput = document.getElementById("month_reference_input");

    if (!modal || !form || !idInput || !dateInput) return;

    idInput.value = id;

    // currentMonth is expected to be YYYY-MM-DD or YYYY-MM
    if (currentMonth) {
        // Extract YYYY-MM
        const match = currentMonth.match(/^(\d{4}-\d{2})/);
        if (match) {
            dateInput.value = match[1];
        } else {
            dateInput.value = "";
        }
    } else {
        dateInput.value = "";
    }

    modal.showModal();
};

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("month_reference_form");
    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();

            const id = document.getElementById("month_reference_file_id").value;
            const month = document.getElementById(
                "month_reference_input"
            ).value; // YYYY-MM

            if (!id || !month) return;

            // Append -01 to make it a full date for the backend validation
            const fullDate = `${month}-01`;

            try {
                const response = await fetch(
                    `/api/processed-files/${id}/month-reference`,
                    {
                        method: "PUT",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            Accept: "application/json",
                        },
                        body: JSON.stringify({ month_reference: fullDate }),
                    }
                );

                if (response.ok) {
                    const data = await response.json();
                    document.getElementById("month_reference_modal").close();

                    // Dispatch event to notify tables to refresh or update
                    window.dispatchEvent(
                        new CustomEvent("processed-file-updated", {
                            detail: { id: id, month_reference: fullDate },
                        })
                    );

                    // Show success message (optional)
                    // alert('Mese di riferimento aggiornato');
                } else {
                    alert("Errore durante l'aggiornamento");
                }
            } catch (error) {
                console.error("Error updating month reference:", error);
                alert("Errore di connessione");
            }
        });
    }
});
