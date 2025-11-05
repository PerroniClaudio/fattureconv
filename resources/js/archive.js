import {
    fetchArchiveList,
    searchArchive,
} from "./archive-files";

document.addEventListener("DOMContentLoaded", () => {
    const yearList = document.getElementById("archive-year-list");
    const monthList = document.getElementById("archive-month-list");
    const searchInput = document.getElementById("archive-search");

    if (!yearList || !monthList) {
        return;
    }

    const state = {
        year: null,
        month: null,
        searchDebounce: null,
    };

    function highlightSelection(list, attr, value) {
        const items = list.querySelectorAll(`[data-${attr}]`);
        items.forEach((item) => {
            const isActive =
                String(item.dataset[attr]) === String(value ?? "");
            item.classList.toggle("active", isActive);
            item.setAttribute("aria-selected", String(isActive));
            const button = item.querySelector("button");
            if (button) {
                button.classList.toggle("btn-active", isActive);
                button.classList.toggle("btn-primary", isActive);
                button.classList.toggle("btn-ghost", !isActive);
            }
        });
    }

    async function refreshArchive() {
        if (!state.year || !state.month) return;

        const query = searchInput ? searchInput.value : "";

        try {
            if (query && query.trim().length > 0) {
                await searchArchive(query, state.year, state.month);
            } else {
                await fetchArchiveList(state.year, state.month);
            }
        } catch (error) {
            // L'errore è già stato gestito nel modulo chiamato.
        }
    }

    function setYear(year) {
        if (!year || year === state.year) return;
        state.year = year;
        highlightSelection(yearList, "year", year);
        refreshArchive();
    }

    function setMonth(month) {
        if (!month || month === state.month) return;
        state.month = month;
        highlightSelection(monthList, "month", month);
        refreshArchive();
    }

    yearList.addEventListener("click", (event) => {
        const item = event.target.closest("[data-year]");
        if (!item || !yearList.contains(item)) return;
        const value = Number.parseInt(item.dataset.year, 10);
        if (Number.isNaN(value)) return;
        setYear(value);
    });

    monthList.addEventListener("click", (event) => {
        const item = event.target.closest("[data-month]");
        if (!item || !monthList.contains(item)) return;
        const value = Number.parseInt(item.dataset.month, 10);
        if (Number.isNaN(value)) return;
        setMonth(value);
    });

    if (searchInput) {
        searchInput.addEventListener("input", () => {
            if (state.searchDebounce) {
                clearTimeout(state.searchDebounce);
            }

            state.searchDebounce = setTimeout(() => {
                refreshArchive();
            }, 300);
        });
    }

    const now = new Date();
    const defaultYear =
        Number.parseInt(
            yearList.querySelector(`[data-year="${now.getFullYear()}"]`)
                ?.dataset.year ?? "",
            10
        ) ||
        Number.parseInt(
            yearList.querySelector("[data-year]")?.dataset.year ?? "",
            10
        );
    const defaultMonth =
        Number.parseInt(
            monthList.querySelector(
                `[data-month="${now.getMonth() + 1}"]`
            )?.dataset.month ?? "",
            10
        ) ||
        Number.parseInt(
            monthList.querySelector("[data-month]")?.dataset.month ?? "",
            10
        );

    if (defaultYear) {
        state.year = defaultYear;
        highlightSelection(yearList, "year", defaultYear);
    }

    if (defaultMonth) {
        state.month = defaultMonth;
        highlightSelection(monthList, "month", defaultMonth);
    }

    refreshArchive();
});
