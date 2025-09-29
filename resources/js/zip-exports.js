import { escapeHtml, formatDate } from './jobs-table.js';

const apiIndex = '/api/zip-exports';
const createEndpoint = '/api/zip-exports';

function renderRow(item, tbody) {
  const tr = document.createElement('tr');
  // display only the basename (strip folder prefixes like "zip-exports/")
  const rawName = item.zip_filename || item.gcs_path || '—';
  const displayName = rawName && rawName !== '—' ? String(rawName).split('/').pop() : '—';
  const filename = escapeHtml(displayName);
  const rawStatus = (item.status || '').toLowerCase();
  const labels = {
    creating_folder: 'Creazione cartella',
    downloading_files: 'Download file',
    creating_zip: 'Creazione ZIP',
    uploading: 'Upload',
    cleaning_up: 'Pulizia',
    completed: 'Completato',
    failed: 'Fallito',
    pending: 'In coda'
  };
  const statusLabel = labels[rawStatus] || (item.status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '—';
  // badge class logic similar to jobs-table
  let badgeClass = 'badge';
  if (['completed'].includes(rawStatus)) badgeClass = 'badge badge-success';
  else if (['failed','error'].includes(rawStatus)) badgeClass = 'badge badge-error';
  else if (['creating_folder','downloading_files','creating_zip','uploading','cleaning_up','pending'].includes(rawStatus)) badgeClass = 'badge badge-secondary';

  const statusHtml = (badgeClass === 'badge badge-secondary')
    ? `<span class="${badgeClass}">${escapeHtml(statusLabel)} <span class="loading loading-spinner loading-xs align-middle"></span></span>`
    : `<span class="${badgeClass}">${escapeHtml(statusLabel)}</span>`;

  const range = `${escapeHtml(item.start_date || '')} — ${escapeHtml(item.end_date || '')}`;
  const actions = item.gcs_path ? `<a class="btn btn-sm btn-primary" href="/api/zip-exports/${encodeURIComponent(item.id)}/download">Download</a>` : `<button class="btn btn-sm btn-ghost" disabled>—</button>`;
  tr.innerHTML = `<th>${escapeHtml(String(item.id))}</th><td>${filename}</td><td class="status-cell">${statusHtml}</td><td>${escapeHtml(range)}</td><td class="actions-cell">${actions}</td>`;
  tbody.appendChild(tr);
}

async function fetchList() {
  const tbody = document.getElementById('zip-exports-tbody');
  try {
    const res = await fetch(apiIndex, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error('Network error');
    const json = await res.json();
    const items = json.items || json || [];
    tbody.innerHTML = '';
    if (!items || items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nessun export</td></tr>';
      return;
    }
    for (const it of items) renderRow(it, tbody);
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-error">Errore nel caricamento</td></tr>';
    console.warn('fetchList error', e);
  }
}

async function createExport(e) {
  e.preventDefault();
  const btn = document.getElementById('createExportBtn');
  btn.disabled = true;
  const start = document.getElementById('start_date').value;
  const end = document.getElementById('end_date').value;
  try {
    const res = await fetch(createEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
      body: JSON.stringify({ start_date: start, end_date: end })
    });
    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || 'Network error');
    }
    const json = await res.json();
    // refresh list
    await fetchList();
  } catch (e) {
    alert('Errore creazione export: ' + (e.message || e));
  } finally {
    btn.disabled = false;
  }
}

export function initZipExports() {
  const form = document.getElementById('zipExportForm');
  if (!form) return;
  form.addEventListener('submit', createExport);
  fetchList();
  setInterval(fetchList, 5000);
}
