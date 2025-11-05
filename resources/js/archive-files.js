const CONTAINER_ID = "archive-list";
const TEMPLATE_ID = "archive-list-item-template";
const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
    "";

const MERGE_POLL_INTERVAL = 3000;
const MERGE_POLL_TIMEOUT = 120000;

const errorStatuses = new Set(["error", "merge_error"]);
const successStatuses = new Set(["merged", "completed"]);
const runningStatuses = new Set(["merging"]);

const downloadRoutes = {
    word: (id) => `/processed-files/${id}/download`,
    pdf: (id) => `/processed-files/${id}/download-merged`,
};

let cachedFiles = [];

const mergeState = {
    dialog: null,
    fileNameEl: null,
    statusEl: null,
    hintEl: null,
    spinnerEl: null,
    errorContainer: null,
    errorTextEl: null,
    startBtn: null,
    downloadBtn: null,
    closeBtn: null,
    initialized: false,
    copyBtn: null,
    fileId: null,
    isRunning: false,
    pollTimer: null,
    pollTimeout: null,
    pollingRequest: false,
    lastError: "",
};

const dateFormatter = new Intl.DateTimeFormat("it-IT", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
});

function getContainer() {
    return document.getElementById(CONTAINER_ID);
}

function getTemplate() {
    const tpl = document.getElementById(TEMPLATE_ID);
    return tpl instanceof HTMLTemplateElement ? tpl : null;
}

function getCachedFileById(id) {
    const numeric = Number(id);
    if (!Number.isFinite(numeric)) return undefined;
    return cachedFiles.find((file) => Number(file?.id) === numeric);
}

function updateCachedFile(id, updates = {}) {
    const file = getCachedFileById(id);
    if (!file) return;
    Object.assign(file, updates);
}

function applyProcessedData(data = {}) {
    if (!data || typeof data.id === "undefined" || data.id === null) {
        return undefined;
    }
    const id = Number(data.id);
    const updates = {};

    if (typeof data.status !== "undefined" && data.status !== null) {
        updates.status = data.status;
    }
    if (typeof data.error_message !== "undefined") {
        updates.error_message = data.error_message;
    }
    if (typeof data.word_path !== "undefined") {
        updates.word_available = Boolean(data.word_path);
        if (updates.word_available) {
            const existing = getCachedFileById(id);
            updates.word_url = existing?.word_url || downloadRoutes.word(id);
        }
    }
    if (typeof data.merged_pdf_path !== "undefined") {
        const hasMerged = Boolean(data.merged_pdf_path);
        updates.pdf_available = hasMerged;
        if (hasMerged) {
            const existing = getCachedFileById(id);
            updates.pdf_url = existing?.pdf_url || downloadRoutes.pdf(id);
        }
    }

    updateCachedFile(id, updates);
    return getCachedFileById(id);
}

function renderMessage(message) {
    const container = getContainer();
    if (!container) return;
    const li = document.createElement("li");
    li.className =
        "list-row flex items-center justify-center text-sm text-muted-content py-8";
    li.textContent = message;
    container.replaceChildren(li);
}

function formatDate(iso) {
    if (!iso) return "";
    const parsed = new Date(iso);
    if (Number.isNaN(parsed.getTime())) return "";
    return dateFormatter.format(parsed);
}

function configureActionButton(button, available, handler, unavailableTitle) {
    if (!button) return;
    button.disabled = !available;
    button.setAttribute("aria-disabled", String(!available));
    button.classList.toggle("btn-disabled", !available);
    button.classList.toggle("cursor-not-allowed", !available);
    if (!available) {
        if (unavailableTitle) button.title = unavailableTitle;
        return;
    }
    button.title = "";
    button.addEventListener("click", (event) => {
        event.preventDefault();
        handler();
    });
}

function handlePdfButton(file) {
    if (!file) return;
    const record = getCachedFileById(file.id) || file;
    if (record.pdf_available && record.pdf_url) {
        downloadPdfFile(record.pdf_url);
        return;
    }
    if (record.merged_pdf_path) {
        downloadPdfFile(downloadRoutes.pdf(record.id));
        return;
    }
    openMergeModal(record);
}

export function renderArchiveList(files = []) {
    const container = getContainer();
    const template = getTemplate();
    cachedFiles = Array.isArray(files) ? files : [];

    if (!container) return;

    if (!cachedFiles.length) {
        renderMessage("Nessun file disponibile per il periodo selezionato.");
        return;
    }

    if (!template) {
        container.replaceChildren(
            ...cachedFiles.map((file) => {
                const fallback = document.createElement("li");
                fallback.className =
                    "list-row flex items-center justify-between w-full gap-4";
                fallback.textContent = file?.name ?? "File";
                return fallback;
            })
        );
        return;
    }

    const fragment = document.createDocumentFragment();

    cachedFiles.forEach((file) => {
        const node = template.content.firstElementChild.cloneNode(true);
        node.dataset.fileId = file?.id ?? "";
        node.dataset.status = file?.status ?? "";

        const name = node.querySelector("[data-file-name]");
        if (name) name.textContent = file?.name ?? "File senza nome";

        const dateLabel = node.querySelector("[data-file-date]");
        if (dateLabel) dateLabel.textContent = formatDate(file?.created_at);

        const wordButton = node.querySelector("[data-download-word]");
        configureActionButton(
            wordButton,
            Boolean(file?.word_available && file?.word_url),
            () => downloadWordFile(file.word_url),
            "Documento Word non disponibile"
        );

        const pdfButton = node.querySelector("[data-download-pdf]");
        if (pdfButton) {
            pdfButton.disabled = false;
            pdfButton.setAttribute("aria-disabled", "false");
            pdfButton.classList.remove("btn-disabled", "cursor-not-allowed");
            pdfButton.title = file?.pdf_available
                ? ""
                : "Avvia l'unione per generare il PDF.";
            pdfButton.addEventListener("click", (event) => {
                event.preventDefault();
                handlePdfButton(file);
            });
        }

        fragment.appendChild(node);
    });

    container.replaceChildren(fragment);
}

export function renderLoading() {
    renderMessage("Caricamento archivi…");
}

export function renderError(message = "Errore durante il caricamento.") {
    renderMessage(message);
}

function ensureMergeModal() {
    if (mergeState.initialized) {
        return mergeState;
    }
    const dialog = document.getElementById("archive-merge-modal");
    if (!dialog) return mergeState;

    mergeState.dialog = dialog;
    mergeState.fileNameEl = dialog.querySelector("[data-merge-file]");
    mergeState.statusEl = dialog.querySelector("[data-merge-status]");
    mergeState.hintEl = dialog.querySelector("[data-merge-hint]");
    mergeState.spinnerEl = dialog.querySelector("[data-merge-spinner]");
    mergeState.errorContainer = dialog.querySelector("[data-merge-error]");
    mergeState.errorTextEl = dialog.querySelector("[data-merge-error-text]");
    mergeState.startBtn = dialog.querySelector("[data-merge-start]");
    mergeState.downloadBtn = dialog.querySelector("[data-merge-download]");
    mergeState.closeBtn = dialog.querySelector("[data-merge-close]");
    mergeState.copyBtn = dialog.querySelector("[data-merge-error-copy]");

    if (mergeState.startBtn) {
        mergeState.startBtn.addEventListener("click", handleMergeStart);
    }
    if (mergeState.downloadBtn) {
        mergeState.downloadBtn.addEventListener("click", handleMergeDownload);
    }
    if (mergeState.closeBtn) {
        mergeState.closeBtn.addEventListener("click", handleMergeClose);
    }
    if (mergeState.copyBtn) {
        mergeState.copyBtn.addEventListener("click", handleCopyError);
    }

    dialog.addEventListener("close", handleModalClosed);
    dialog.addEventListener("cancel", (event) => {
        if (mergeState.isRunning) {
            event.preventDefault();
        }
    });

    mergeState.initialized = true;
    return mergeState;
}

function handleMergeClose() {
    if (mergeState.isRunning) return;
    mergeState.dialog?.close();
}

function handleModalClosed() {
    stopPolling();
    mergeState.fileId = null;
    mergeState.isRunning = false;
    mergeState.lastError = "";
    setModalError("");
}

function setModalHint(message) {
    if (!mergeState.hintEl) return;
    mergeState.hintEl.textContent =
        message ||
        "L'unione genera un PDF combinando il documento originale con quello elaborato.";
}

function setModalError(message = "") {
    mergeState.lastError = message || "";
    if (!mergeState.errorContainer || !mergeState.errorTextEl) return;
    const hasError = Boolean(message);
    mergeState.errorContainer.classList.toggle("hidden", !hasError);
    mergeState.errorTextEl.textContent = message || "";
    if (mergeState.copyBtn) {
        mergeState.copyBtn.disabled = !hasError;
        mergeState.copyBtn.classList.toggle("btn-disabled", !hasError);
    }
}

function setModalRunning(running) {
    mergeState.isRunning = running;
    if (mergeState.closeBtn) {
        mergeState.closeBtn.disabled = running;
    }
    refreshModalView();
}

function refreshModalView() {
    if (mergeState.fileId === null || mergeState.fileId === undefined) return;
    const file = getCachedFileById(mergeState.fileId);
    if (!file) return;

    if (mergeState.fileNameEl) {
        mergeState.fileNameEl.textContent =
            file.name || `File ID ${mergeState.fileId}`;
    }

    const status = (file.status || "").toLowerCase();
    let statusMessage = "";
    let showSpinner = mergeState.isRunning || runningStatuses.has(status);

    if (file.pdf_available && file.pdf_url) {
        statusMessage = "PDF unito disponibile per il download.";
        showSpinner = false;
        setModalHint(
            "Scarica il PDF generato oppure chiudi il modal per tornare alla lista."
        );
        setModalError("");
    } else if (mergeState.isRunning || runningStatuses.has(status)) {
        statusMessage = "Unione in corso…";
        setModalHint(
            "Attendi il completamento dell'unione. Il modal rimarrà aperto fino a quando il PDF è pronto."
        );
    } else if (errorStatuses.has(status)) {
        statusMessage = "Si è verificato un errore durante l'unione.";
        showSpinner = false;
        if (!mergeState.lastError) {
            setModalError(
                file.error_message ||
                    "Errore durante l'unione del PDF. Riprova ad avviare il job."
            );
        }
        setModalHint(
            "Riprova ad avviare l'unione. Se l'errore persiste contatta il supporto."
        );
    } else {
        statusMessage =
            "Il PDF unito non è ancora disponibile. Avvia il job di unione per generarlo.";
        showSpinner = false;
        setModalHint(
            "L'unione richiede alcuni secondi. Il modal rimarrà aperto fino al completamento."
        );
        setModalError("");
    }

    if (mergeState.statusEl) {
        mergeState.statusEl.textContent = statusMessage;
    }
    if (mergeState.spinnerEl) {
        mergeState.spinnerEl.classList.toggle("hidden", !showSpinner);
    }
    if (mergeState.startBtn) {
        const canStart =
            !file.pdf_available &&
            !mergeState.isRunning &&
            !runningStatuses.has(status);
        mergeState.startBtn.classList.toggle("hidden", !canStart);
        mergeState.startBtn.disabled = !canStart;
    }
    if (mergeState.downloadBtn) {
        const canDownload = Boolean(file.pdf_available && file.pdf_url);
        mergeState.downloadBtn.classList.toggle("hidden", !canDownload);
        mergeState.downloadBtn.disabled = !canDownload;
    }
}

function openMergeModal(file) {
    const elements = ensureMergeModal();
    if (!elements.dialog || !file) return;

    const target = getCachedFileById(file.id) || file;
    const id = Number(target.id);
    if (!Number.isFinite(id)) return;

    mergeState.fileId = id;
    mergeState.lastError = "";
    setModalError("");

    const status = (target.status || "").toLowerCase();
    const shouldRun = runningStatuses.has(status);

    setModalRunning(shouldRun);
    if (shouldRun) {
        startPolling(true);
    } else {
        stopPolling();
        refreshModalView();
    }

    if (!elements.dialog.open) {
        elements.dialog.showModal();
    }

    refreshModalView();
}

async function handleMergeStart() {
    if (!Number.isFinite(mergeState.fileId)) return;
    setModalError("");
    setModalRunning(true);

    try {
        const payload = await startMergeJob(mergeState.fileId);

        if (payload?.processed_file) {
            applyProcessedData(payload.processed_file);
        }

        if (payload?.already_merged) {
            onMergeSuccess(payload.processed_file || { id: mergeState.fileId });
            return;
        }

        if (payload?.already_in_progress) {
            startPolling(true);
            return;
        }

        startPolling(true);
    } catch (error) {
        console.error("Errore durante l'avvio del merge:", error);
        setModalRunning(false);
        setModalError(
            error?.message || "Errore durante l'avvio del job di unione."
        );
    }
}

function handleMergeDownload() {
    const file = getCachedFileById(mergeState.fileId);
    if (file?.pdf_available && file?.pdf_url) {
        downloadPdfFile(file.pdf_url);
    }
}

function startPolling(immediate = false) {
    if (!Number.isFinite(mergeState.fileId)) return;
    stopPolling();
    mergeState.pollTimer = window.setInterval(pollStatus, MERGE_POLL_INTERVAL);
    mergeState.pollTimeout = window.setTimeout(
        onPollTimeout,
        MERGE_POLL_TIMEOUT
    );
    if (immediate) {
        pollStatus();
    }
}

function stopPolling() {
    if (mergeState.pollTimer) {
        clearInterval(mergeState.pollTimer);
        mergeState.pollTimer = null;
    }
    if (mergeState.pollTimeout) {
        clearTimeout(mergeState.pollTimeout);
        mergeState.pollTimeout = null;
    }
    mergeState.pollingRequest = false;
}

function onPollTimeout() {
    stopPolling();
    setModalRunning(false);
    setModalError(
        "Timeout durante l'unione del PDF. Riprova ad avviare il job."
    );
}

async function pollStatus() {
    if (mergeState.pollingRequest) return;
    if (!Number.isFinite(mergeState.fileId)) return;

    mergeState.pollingRequest = true;
    try {
        const response = await fetch("/api/processed-files/statuses", {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify({ ids: [mergeState.fileId] }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                payload?.error || `Errore HTTP ${response.status}`
            );
        }

        const record = payload?.[String(mergeState.fileId)];
        if (!record) {
            throw new Error("File non trovato durante il polling.");
        }

        handleStatusEntry(record);
    } catch (error) {
        console.error("Errore durante il polling del merge:", error);
        stopPolling();
        setModalRunning(false);
        setModalError(
            error?.message ||
                "Errore durante la verifica dello stato del job di unione."
        );
    } finally {
        mergeState.pollingRequest = false;
    }
}

function handleStatusEntry(entry) {
    const updated = applyProcessedData(entry) || entry;
    const status = (entry.status || updated.status || "").toLowerCase();
    const hasPdf =
        Boolean(entry.merged_pdf_path) ||
        Boolean(updated.pdf_available && updated.pdf_url);

    if (hasPdf || successStatuses.has(status)) {
        onMergeSuccess(entry);
        return;
    }

    if (errorStatuses.has(status)) {
        const message =
            entry.error_message ||
            updated.error_message ||
            "Errore durante l'unione del PDF.";
        onMergeFailure(message, entry);
        return;
    }

    setModalRunning(true);
}

function onMergeSuccess(entry) {
    stopPolling();
    applyProcessedData(entry);
    setModalRunning(false);
    setModalError("");
    refreshModalView();
}

function onMergeFailure(message, entry) {
    stopPolling();
    applyProcessedData(entry);
    setModalRunning(false);
    setModalError(message);
    refreshModalView();
}

function handleCopyError() {
    const message = mergeState.lastError;
    if (!message) return;
    if (!navigator?.clipboard?.writeText) {
        alert("Copia negli appunti non supportata in questo browser.");
        return;
    }
    navigator.clipboard
        .writeText(message)
        .then(() => {
            const originalText =
                mergeState.copyBtn?.textContent || "Copia errore";
            if (mergeState.copyBtn) {
                mergeState.copyBtn.textContent = "Copiato!";
                mergeState.copyBtn.disabled = true;
                setTimeout(() => {
                    if (!mergeState.copyBtn) return;
                    mergeState.copyBtn.textContent = originalText;
                    mergeState.copyBtn.disabled = false;
                }, 1500);
            }
        })
        .catch(() => {
            alert("Impossibile copiare negli appunti.");
        });
}

async function startMergeJob(id) {
    const response = await fetch(`/api/processed-files/${id}/merge`, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload?.error || `Errore HTTP ${response.status}`);
    }

    return payload;
}

export async function fetchArchiveList(year, month) {
    renderLoading();
    try {
        const params = new URLSearchParams({
            year: String(year),
            month: String(month),
        });
        const response = await fetch(`/api/archive?${params.toString()}`, {
            headers: { Accept: "application/json" },
        });
        if (!response.ok) {
            throw new Error(`Errore ${response.status}`);
        }
        const payload = await response.json();
        const items = Array.isArray(payload?.data) ? payload.data : [];
        renderArchiveList(items);
        return items;
    } catch (error) {
        console.error("Impossibile ottenere l'archivio", error);
        renderError("Impossibile ottenere l'archivio selezionato.");
        throw error;
    }
}

export async function searchArchive(query, year, month) {
    const trimmed = (query || "").trim();
    if (!trimmed) {
        return fetchArchiveList(year, month);
    }

    renderLoading();

    try {
        const params = new URLSearchParams({
            year: String(year),
            month: String(month),
            query: trimmed,
        });
        const response = await fetch(
            `/api/archive/search?${params.toString()}`,
            {
                headers: { Accept: "application/json" },
            }
        );
        if (!response.ok) {
            throw new Error(`Errore ${response.status}`);
        }
        const payload = await response.json();
        const items = Array.isArray(payload?.data) ? payload.data : [];
        renderArchiveList(items);
        return items;
    } catch (error) {
        console.error("Errore durante la ricerca nell'archivio", error);
        renderError("Errore durante la ricerca nell'archivio.");
        throw error;
    }
}

export function downloadWordFile(url) {
    triggerDownload(url);
}

export function downloadPdfFile(url) {
    triggerDownload(url);
}

function triggerDownload(url) {
    if (!url) return;
    const link = document.createElement("a");
    link.href = url;
    link.rel = "noopener";
    link.target = "_blank";
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

export function getCachedFiles() {
    return cachedFiles.slice();
}
