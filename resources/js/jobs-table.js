// jobs-table.js - ES module

const statusesEndpoint = '/api/processed-files/statuses';
const inProgressEndpoint = '/api/processed-files/in-progress';
const processedIndexEndpoint = '/api/processed-files';

export function escapeHtml(unsafe) {
  if (unsafe === null || unsafe === undefined) return '';
  return String(unsafe)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export function formatDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d)) return escapeHtml(String(iso));
  const pad = (n) => String(n).padStart(2, '0');
  const day = pad(d.getDate());
  const month = pad(d.getMonth() + 1);
  const year = d.getFullYear();
  const hours = pad(d.getHours());
  const minutes = pad(d.getMinutes());
  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

export function renderRow(item, inProgressTable) {
  const tbody = inProgressTable.querySelector('tbody');
  let tr = inProgressTable.querySelector(`tr[data-id="${item.id}"]`);
  if (tr) return tr;
  const fileName = escapeHtml(item.original_filename || item.gcs_path || '—');
  const date = formatDate(item.created_at || '');
  const status = (item.status || '').toLowerCase();
  const labels = {
    pending: 'In coda', uploaded: 'Caricato', parsing_pdf: 'Estrazione PDF', calling_ai: 'Analisi dati', ai_completed: 'Analisi completata', generating_word: 'Generazione documento', word_generated: 'Documento generato', uploading_word: 'Upload documento', completed: 'Completato', word_missing: 'Documento mancante', processing: 'In elaborazione', error: 'Errore'
  };
  const label = escapeHtml(labels[status] || (item.status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '—');
  tr = document.createElement('tr');
  tr.setAttribute('data-id', item.id);
  tr.innerHTML = `
    <th>${escapeHtml(String(item.id))}</th>
    <td>${fileName}</td>
    <td class="status-cell">${label}</td>
    <td>${date}</td>
    <td class="actions-cell"><button class="btn btn-sm btn-ghost" disabled>Non disponibile</button></td>
  `;
  tbody.appendChild(tr);
  return tr;
}

export function updateRow(id, data, inProgressTable) {
  const tr = inProgressTable.querySelector(`tr[data-id="${id}"]`);
  if (!tr) return;
  const statusCell = tr.querySelector('.status-cell');
  const actionsCell = tr.querySelector('.actions-cell');
  const status = (data.status || '').toLowerCase();
  const labels = {
    pending: 'In coda', uploaded: 'Caricato', parsing_pdf: 'Estrazione PDF', calling_ai: 'Analisi dati', ai_completed: 'Analisi completata', generating_word: 'Generazione documento', word_generated: 'Documento generato', uploading_word: 'Upload documento', completed: 'Completato', word_missing: 'Documento mancante', processing: 'In elaborazione', error: 'Errore'
  };
  const label = labels[status] || (data.status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '—';
  if (status === 'error' || status === 'errore') {
    const msg = data.error_message || 'Errore non disponibile';
    const structured = JSON.stringify(data.structured_json || '', null, 2);
    const extracted = data.extracted_text || '';
    const fileAttr = data.original_filename || data.gcs_path || '';
    const createdAt = data.created_at || '';
    const wordPath = data.word_path || '';
    statusCell.innerHTML = `<button class="badge badge-error" title="${escapeHtml(msg)}" data-error="${escapeHtml(msg)}" data-structured='${escapeHtml(structured)}' data-extracted='${escapeHtml(extracted)}' data-file="${escapeHtml(fileAttr)}" data-created_at="${escapeHtml(createdAt)}" data-id="${escapeHtml(id)}" data-word_path="${escapeHtml(wordPath)}" onclick="showErrorElement(this)">${escapeHtml(label)}</button>`;
  } else {
    let badgeClass = 'badge';
    if (['processed','completed','ai_completed','completato'].includes(status)) badgeClass = 'badge badge-success';
    else if (['uploaded','pending','processing','parsing_pdf','calling_ai','generating_word','uploading_word'].includes(status)) badgeClass = 'badge badge-secondary';
    if (['uploaded','pending','processing','parsing_pdf','calling_ai','generating_word','uploading_word'].includes(status)) {
      statusCell.innerHTML = `<span class="${badgeClass}">${escapeHtml(label)} <span class="loading loading-spinner loading-xs align-middle"></span></span>`;
    } else {
      statusCell.innerHTML = `<span class="${badgeClass}">${escapeHtml(label)}</span>`;
    }
  }
  if (data.word_path) {
    actionsCell.innerHTML = `<a class="btn btn-sm btn-primary" href="/processed-files/${id}/download" data-download-url="/processed-files/${id}/download">Download</a>`;
  } else {
    actionsCell.innerHTML = `<button class="btn btn-sm btn-ghost" disabled>Non disponibile</button>`;
  }
}

export function renderCompletedRow(row, completedTbody) {
  const tr = document.createElement('tr');
  const fileName = escapeHtml(row.original_filename || row.gcs_path || '—');
  const date = formatDate(row.created_at || '');
  const status = (row.status || '').toLowerCase();
  const labels = { pending: 'In coda', uploaded: 'Caricato', parsing_pdf: 'Estrazione PDF', calling_ai: 'Analisi dati', ai_completed: 'Analisi completata', generating_word: 'Generazione documento', word_generated: 'Documento generato', uploading_word: 'Upload documento', completed: 'Completato', word_missing: 'Documento mancante', processing: 'In elaborazione', error: 'Errore' };
  const label = escapeHtml(labels[status] || (row.status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '—');
  let badgeClass = 'badge';
  if (['processed','completed','ai_completed','completato'].includes(status)) badgeClass = 'badge badge-success';
  else if (['error','errore'].includes(status)) badgeClass = 'badge badge-error';
  else if (['uploaded','pending','processing','parsing_pdf','calling_ai','generating_word','uploading_word'].includes(status)) badgeClass = 'badge badge-secondary';
  const statusHtml = (badgeClass === 'badge badge-error')
    ? `<button class="badge badge-error" title="${escapeHtml(row.error_message || '')}" data-error="${escapeHtml(row.error_message || '')}" data-structured='${escapeHtml(JSON.stringify(row.structured_json || ''))}' data-extracted='${escapeHtml(row.extracted_text || '')}' data-file="${escapeHtml(fileName)}" data-created_at="${escapeHtml(row.created_at || '')}" data-id="${escapeHtml(row.id)}" data-word_path="${escapeHtml(row.word_path || '')}" onclick="showErrorElement(this)">${escapeHtml(label)}</button>`
    : `<span class="${badgeClass}">${escapeHtml(label)}</span>`;
  const actions = row.word_path ? `<a class="btn btn-sm btn-primary" href="/processed-files/${row.id}/download" data-download-url="/processed-files/${row.id}/download">Download</a>` : `<button class="btn btn-sm btn-ghost" disabled>Non disponibile</button>`;
  tr.innerHTML = `<th>${escapeHtml(String(row.id))}</th><td>${fileName}</td><td class="status-cell">${statusHtml}</td><td>${date}</td><td class="actions-cell">${actions}</td>`;
  completedTbody.appendChild(tr);
}

export function showErrorElement(el) {
  try {
    const dialog = document.getElementById('errorModal');
    const file = el.getAttribute('data-file') || '';
    const createdAt = el.getAttribute('data-created_at') || '';
    const id = el.getAttribute('data-id') || '';
    const wordPath = el.getAttribute('data-word_path') || '';
    const error = el.getAttribute('data-error') || '';
    const structured = el.getAttribute('data-structured') || '';
    const extracted = el.getAttribute('data-extracted') || '';
    document.getElementById('modalFileName').innerText = file || id;
    document.getElementById('modalMeta').innerText = `ID: ${id} • Creato: ${formatDate(createdAt)} • Word: ${wordPath}`;
    document.getElementById('modalErrorMessage').innerText = error;
    document.getElementById('modalStructuredJson').innerText = structured || '';
    document.getElementById('modalExtractedText').value = extracted || '';
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.style.display = 'block';
    }
  } catch (e) {
    alert('Impossibile aprire il report dettagliato: ' + e.message);
  }
}

export function copyToClipboard(text) {
  if (!text) return;
  navigator.clipboard.writeText(text).then(() => {
    alert('Copiato negli appunti');
  }).catch(() => {
    alert('Copia non riuscita');
  });
}

export function downloadStructuredJson() {
  const content = document.getElementById('modalStructuredJson').innerText || '';
  if (!content) { alert('Nessun JSON disponibile'); return; }
  const blob = new Blob([content], {type: 'application/json'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'structured.json';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function initJobsTable() {
  const inProgressTable = document.getElementById('in-progress-table');
  const completedTbody = document.getElementById('completed-tbody');
  const completedPrev = document.getElementById('completed-prev');
  const completedNext = document.getElementById('completed-next');
  const completedInfo = document.getElementById('completed-pagination-info');
  const completedTab = document.querySelector('[data-tab=\"completed\"]');
  const inProgressTab = document.querySelector('[data-tab=\"in-progress\"]');
  const tabInProgressPanel = document.getElementById('tab-in-progress');
  const tabCompletedPanel = document.getElementById('tab-completed');

  let completedPage = 1;
  let completedPerPage = 10;
  let completedLastPage = 1;

  async function fetchCompletedPage(page = 1) {
    try {
      completedTbody.innerHTML = '<tr><td colspan=\"5\" class=\"text-center\"><span class=\"loading loading-spinner loading-lg\"></span></td></tr>';
      const res = await fetch(`${processedIndexEndpoint}?status=completed&page=${page}&per_page=${completedPerPage}`, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('Network response not ok');
      const json = await res.json();
      const items = json.items || [];
      const meta = json.meta || { total: 0, page: 1, per_page: completedPerPage, last_page: 1 };
      completedPage = meta.page || 1;
      completedPerPage = meta.per_page || completedPerPage;
      completedLastPage = meta.last_page || 1;
      completedTbody.innerHTML = '';
      if (items.length === 0) {
        completedTbody.innerHTML = '<tr><td colspan=\"5\" class=\"text-center\">Nessun job completato nella pagina corrente</td></tr>';
      } else {
        for (const row of items) renderCompletedRow(row, completedTbody);
      }
      completedPrev.disabled = completedPage <= 1;
      completedNext.disabled = completedPage >= completedLastPage;
      completedInfo.innerText = `Pagina ${completedPage} di ${completedLastPage} — Totale ${meta.total}`;
      return json;
    } catch (e) {
      completedTbody.innerHTML = '<tr><td colspan=\"5\" class=\"text-center text-error\">Errore nel caricamento</td></tr>';
      console.warn('fetchCompletedPage error', e);
      return null;
    }
  }

  completedPrev.addEventListener('click', () => { if (completedPage > 1) fetchCompletedPage(completedPage - 1); });
  completedNext.addEventListener('click', () => { if (completedPage < completedLastPage) fetchCompletedPage(completedPage + 1); });

  function setActiveTab(tabName, skipFetch = false) {
    if (tabName === 'in-progress') {
      inProgressTab.classList.add('tab-active');
      completedTab.classList.remove('tab-active');
      tabInProgressPanel.classList.remove('hidden');
      tabCompletedPanel.classList.add('hidden');
    } else {
      completedTab.classList.add('tab-active');
      inProgressTab.classList.remove('tab-active');
      tabCompletedPanel.classList.remove('hidden');
      tabInProgressPanel.classList.add('hidden');
      if (!skipFetch) fetchCompletedPage(completedPage);
    }
  }

  inProgressTab.addEventListener('click', () => setActiveTab('in-progress'));
  completedTab.addEventListener('click', () => setActiveTab('completed'));

  async function pollInProgress() {
    try {
      if (tabInProgressPanel.classList.contains('hidden')) return;
      const res = await fetch(inProgressEndpoint, { method: 'GET', headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('Network response was not ok');
      const payload = await res.json();
      if (!Array.isArray(payload)) return;
      const tbody = inProgressTable.querySelector('tbody');
      if (payload.length === 0) {
        try {
          const json = await fetchCompletedPage(1);
          const items = (json && Array.isArray(json.items)) ? json.items : [];
          if (items.length > 0) {
            setActiveTab('completed', true);
            return;
          }
        } catch (e) {}
        tbody.innerHTML = '<tr class=\"no-jobs-row\"><td colspan=\"5\" class=\"text-center\">Nessun job in corso</td></tr>';
        return;
      }
      const loadingRow = tbody.querySelector('.loading-row');
      if (loadingRow) loadingRow.remove();
      const noJobsRow = tbody.querySelector('.no-jobs-row');
      if (noJobsRow) noJobsRow.remove();
      const received = {};
      for (const item of payload) {
        received[item.id] = item;
        renderRow(item, inProgressTable);
        updateRow(item.id, item, inProgressTable);
      }
      inProgressTable.querySelectorAll('tbody tr[data-id]').forEach(tr => {
        const id = tr.getAttribute('data-id');
        if (!received[id]) tr.remove();
      });
    } catch (e) {
      console.warn('Polling in-progress error:', e.message || e);
    }
  }

  pollInProgress();
  const interval = setInterval(pollInProgress, 3000);
  window.addEventListener('beforeunload', function(){ clearInterval(interval); });
}