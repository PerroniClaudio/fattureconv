import {
    renderCompletedRow,
    deleteProcessedFile,
    showErrorElement,
    copyToClipboard,
    downloadStructuredJson,
} from "./jobs-table.js";

const processedIndexEndpoint = "/api/processed-files";

export function initErrorsTable() {
    const errorsTbody = document.getElementById("errors-tbody");
    const errorsPrev = document.getElementById("errors-prev");
    const errorsNext = document.getElementById("errors-next");
    const errorsInfo = document.getElementById("errors-pagination-info");

    if (!errorsTbody || !errorsPrev || !errorsNext || !errorsInfo) return;

    let errorsPage = 1;
    let errorsPerPage = 10;
    let errorsLastPage = 1;

    async function fetchErrorsPage(page = 1) {
        try {
            errorsTbody.innerHTML =
                '<tr><td colspan="5" class="text-center"><span class="loading loading-spinner loading-lg"></span></td></tr>';
            const res = await fetch(
                `${processedIndexEndpoint}?status=errors&page=${page}&per_page=${errorsPerPage}`,
                { headers: { Accept: "application/json" } }
            );
            if (!res.ok) throw new Error("Network response not ok");
            const json = await res.json();
            const items = json.items || [];
            const meta = json.meta || {
                total: 0,
                page: 1,
                per_page: errorsPerPage,
                last_page: 1,
            };
            errorsPage = meta.page || 1;
            errorsPerPage = meta.per_page || errorsPerPage;
            errorsLastPage = meta.last_page || 1;
            errorsTbody.innerHTML = "";
            if (items.length === 0) {
                errorsTbody.innerHTML =
                    '<tr><td colspan="5" class="text-center">Nessun errore nella pagina corrente</td></tr>';
            } else {
                for (const row of items) renderCompletedRow(row, errorsTbody);
            }
            errorsPrev.disabled = errorsPage <= 1;
            errorsNext.disabled = errorsPage >= errorsLastPage;
            errorsInfo.innerText = `Pagina ${errorsPage} di ${errorsLastPage} — Totale ${meta.total}`;
            return json;
        } catch (e) {
            errorsTbody.innerHTML =
                '<tr><td colspan="5" class="text-center text-error">Errore nel caricamento</td></tr>';
            console.warn("fetchErrorsPage error", e);
            return null;
        }
    }

    errorsPrev.addEventListener("click", () => {
        if (errorsPage > 1) fetchErrorsPage(errorsPage - 1);
    });
    errorsNext.addEventListener("click", () => {
        if (errorsPage < errorsLastPage) fetchErrorsPage(errorsPage + 1);
    });

    fetchErrorsPage(1);

    window.deleteProcessedFile = deleteProcessedFile;
    window.showErrorElement = showErrorElement;
    window.copyToClipboard = copyToClipboard;
    window.downloadStructuredJson = downloadStructuredJson;
}
