<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <h2 class="card-title">File</h2>

    <div class="flex flex-col items-center justify-center">
      <div role="tablist" class="tabs tabs-box">
        <a role="tab" class="tab tab-active" data-tab="in-progress">In corso</a>
        <a role="tab" class="tab " data-tab="completed">Completati</a>
      </div>
    </div>

    @php
      $items = isset($processedFiles) ? collect($processedFiles->items()) : collect([]);
      $inProgressStatuses = ['pending','uploaded','processing','parsing_pdf','calling_ai','generating_word','uploading_word'];
      $completedStatuses = ['ai_completed','word_generated','completed','processed','word_missing'];
      $inProgress = $items->filter(function($r) use ($inProgressStatuses){ return in_array(strtolower($r->status ?? ''), $inProgressStatuses); });
      $completed = $items->filter(function($r) use ($completedStatuses){ return in_array(strtolower($r->status ?? ''), $completedStatuses); });
    @endphp

    <div id="tab-in-progress" class="tab-panel">
      <div class="overflow-x-auto">
        <table id="in-progress-table" class="table w-full">
          <thead>
            <tr>
              <th>#</th>
              <th>File</th>
              <th>Stato</th>
              <th>Data</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <tr class="loading-row"><td colspan="5" class="text-center">Caricamento...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab-completed" class="tab-panel hidden">
      <div class="overflow-x-auto">
        <table id="completed-table" class="table w-full">
          <thead>
            <tr>
              <th>#</th>
              <th>File</th>
              <th>Stato</th>
              <th>Data</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody id="completed-tbody">
            {{-- populated by client-side JS --}}
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-between">
        <div>
          <button id="completed-prev" class="btn btn-sm btn-outline" disabled>
            <x-lucide-arrow-left class="inline-block w-4 h-4 mr-1" />
          </button>
          <button id="completed-next" class="btn btn-sm btn-outline ml-2" disabled>
            <x-lucide-arrow-right class="inline-block w-4 h-4 mr-1" />
          </button>
        </div>
        <div class="text-sm text-muted" id="completed-pagination-info"></div>
      </div>
    </div>
    <!-- Modal dettagli errori -->
    <dialog id="errorModal" class="modal">
      <form method="dialog" class="modal-box w-11/12 max-w-4xl">
        <h3 class="font-bold text-lg">Report errore - <span id="modalFileName">&mdash;</span></h3>
        <p class="text-sm opacity-70" id="modalMeta">&mdash;</p>

        <div class="mt-4 grid grid-cols-1 gap-4">
          <div>
            <h4 class="font-semibold">Messaggio errore</h4>
            <pre id="modalErrorMessage" class="whitespace-pre-wrap bg-base-200 p-3 rounded text-sm"></pre>
            <div class="mt-2">
              <button type="button" class="btn btn-sm btn-outline" onclick="copyToClipboard(document.getElementById('modalErrorMessage').innerText)">Copia messaggio</button>
            </div>
          </div>

          <div>
            <h4 class="font-semibold">Dati strutturati (JSON)</h4>
            <pre id="modalStructuredJson" class="whitespace-pre-wrap bg-base-200 p-3 rounded text-sm max-h-48 overflow-auto"></pre>
            <div class="mt-2">
              <button type="button" class="btn btn-sm btn-outline" onclick="downloadStructuredJson()">Scarica JSON</button>
            </div>
          </div>

          <div>
            <h4 class="font-semibold">Testo estratto</h4>
            <textarea id="modalExtractedText" class="textarea textarea-bordered w-full h-40" readonly></textarea>
            <div class="mt-2">
              <button type="button" class="btn btn-sm btn-outline" onclick="copyToClipboard(document.getElementById('modalExtractedText').value)">Copia testo</button>
            </div>
          </div>
        </div>

        <div class="modal-action">
          <button class="btn" onclick="document.getElementById('errorModal').close()">Chiudi</button>
        </div>
      </form>
    </dialog>

    <script>
      (function(){
        const statusesEndpoint = '/api/processed-files/statuses'; // POST { ids: [..] }
        const inProgressEndpoint = '/api/processed-files/in-progress'; // GET
        const processedIndexEndpoint = '/api/processed-files'; // GET with ?status=...&page=&per_page=
        const inProgressTable = document.getElementById('in-progress-table');
        const completedTbody = document.getElementById('completed-tbody');
        const completedPrev = document.getElementById('completed-prev');
        const completedNext = document.getElementById('completed-next');
        const completedInfo = document.getElementById('completed-pagination-info');
        const completedTab = document.querySelector('[data-tab="completed"]');
        const inProgressTab = document.querySelector('[data-tab="in-progress"]');
        const tabInProgressPanel = document.getElementById('tab-in-progress');
        const tabCompletedPanel = document.getElementById('tab-completed');

  let completedPage = 1;
  let completedPerPage = 10;
        let completedLastPage = 1;

        function escapeHtml(unsafe) {
          if (unsafe === null || unsafe === undefined) return '';
          return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        // format ISO date to d/m/Y H:i (e.g. 29/09/2025 14:05)
        function formatDate(iso) {
          if (!iso) return '';
          // try to parse as Date
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

        // render a row HTML for an in-progress item and insert if missing
        function renderRow(item) {
          const tbody = inProgressTable.querySelector('tbody');
          let tr = inProgressTable.querySelector(`tr[data-id="${item.id}"]`);
          if (tr) return tr; // already present

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

        // aggiorna una riga dato l'id e i dati di risposta
        function updateRow(id, data) {
          const tr = inProgressTable.querySelector(`tr[data-id="${id}"]`);
          if (!tr) return;

          const statusCell = tr.querySelector('.status-cell');
          const actionsCell = tr.querySelector('.actions-cell');

          const status = (data.status || '').toLowerCase();
          const labels = {
            pending: 'In coda', uploaded: 'Caricato', parsing_pdf: 'Estrazione PDF', calling_ai: 'Analisi dati', ai_completed: 'Analisi completata', generating_word: 'Generazione documento', word_generated: 'Documento generato', uploading_word: 'Upload documento', completed: 'Completato', word_missing: 'Documento mancante', processing: 'In elaborazione', error: 'Errore'
          };
          const label = labels[status] || (data.status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '—';

          // costruisci nuovo contenuto per lo status
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

          // aggiorna pulsante download in base a word_path
          if (data.word_path) {
            actionsCell.innerHTML = `<a class="btn btn-sm btn-primary" href="/processed-files/${id}/download" data-download-url="/processed-files/${id}/download">Download</a>`;
          } else {
            actionsCell.innerHTML = `<button class="btn btn-sm btn-ghost" disabled>Non disponibile</button>`;
          }
        }

        // Completed: render a completed row
        function renderCompletedRow(row) {
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

        async function fetchCompletedPage(page = 1) {
          try {
            completedTbody.innerHTML = '<tr><td colspan="5" class="text-center">Caricamento...</td></tr>';
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
              completedTbody.innerHTML = '<tr><td colspan="5" class="text-center">Nessun job completato nella pagina corrente</td></tr>';
            } else {
              for (const row of items) renderCompletedRow(row);
            }

            // update controls
            completedPrev.disabled = completedPage <= 1;
            completedNext.disabled = completedPage >= completedLastPage;
            completedInfo.innerText = `Pagina ${completedPage} di ${completedLastPage} — Totale ${meta.total}`;
            return json;
          } catch (e) {
            completedTbody.innerHTML = '<tr><td colspan="5" class="text-center text-error">Errore nel caricamento</td></tr>';
            console.warn('fetchCompletedPage error', e);
            return null;
          }
        }

        completedPrev.addEventListener('click', () => { if (completedPage > 1) fetchCompletedPage(completedPage - 1); });
        completedNext.addEventListener('click', () => { if (completedPage < completedLastPage) fetchCompletedPage(completedPage + 1); });

        // Switch tabs
        // skipFetch = true prevents setActiveTab from calling fetchCompletedPage again (used when we already fetched)
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
            // when completed tab is shown, ensure first page is loaded (unless caller already fetched)
            if (!skipFetch) fetchCompletedPage(completedPage);
          }
        }

        inProgressTab.addEventListener('click', () => setActiveTab('in-progress'));
        completedTab.addEventListener('click', () => setActiveTab('completed'));

        // Poll the in-progress endpoint and update/replace rows as needed
        async function pollInProgress() {
          try {
            // only poll if the in-progress tab is visible
            if (tabInProgressPanel.classList.contains('hidden')) return;

            const res = await fetch(inProgressEndpoint, { method: 'GET', headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('Network response was not ok');
            const payload = await res.json(); // array of items

            if (!Array.isArray(payload)) return;
            const tbody = inProgressTable.querySelector('tbody');

            // If empty result, show friendly message instead of leaving 'Caricamento...'
            if (payload.length === 0) {
              // try to switch automatically to Completed tab if there are completed items
              try {
                const json = await fetchCompletedPage(1);
                const items = (json && Array.isArray(json.items)) ? json.items : [];
                if (items.length > 0) {
                  // switch to completed without triggering an extra fetch (we already fetched page 1)
                  setActiveTab('completed', true);
                  return;
                }
              } catch (e) {
                // ignore errors and fall back to showing 'Nessun job in corso'
              }

              tbody.innerHTML = '<tr class="no-jobs-row"><td colspan="5" class="text-center">Nessun job in corso</td></tr>';
              return;
            }

            // remove initial loading row if present
            const loadingRow = tbody.querySelector('.loading-row');
            if (loadingRow) loadingRow.remove();
            const noJobsRow = tbody.querySelector('.no-jobs-row');
            if (noJobsRow) noJobsRow.remove();

            // Build a map of received items and render/update rows
            const received = {};
            for (const item of payload) {
              received[item.id] = item;
              // render row if missing
              renderRow(item);
              updateRow(item.id, item);
            }

            // Remove rows that are no longer in the in-progress list
            inProgressTable.querySelectorAll('tbody tr[data-id]').forEach(tr => {
              const id = tr.getAttribute('data-id');
              if (!received[id]) tr.remove();
            });
          } catch (e) {
            console.warn('Polling in-progress error:', e.message || e);
          }
        }

        // export global utilities used by inline HTML (modal)
        window.showErrorElement = function(el) {
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
        };

        window.copyToClipboard = function(text) {
          if (!text) return;
          navigator.clipboard.writeText(text).then(() => {
            alert('Copiato negli appunti');
          }).catch(() => {
            alert('Copia non riuscita');
          });
        };

        window.downloadStructuredJson = function() {
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
        };

        // Start polling every 3 seconds (only for in-progress)
        pollInProgress();
        const interval = setInterval(pollInProgress, 3000);
        window.addEventListener('beforeunload', function(){ clearInterval(interval); });

        // also pre-load first completed page lazily if user switches to that tab
      })();
    </script>
  </div>
</div>
