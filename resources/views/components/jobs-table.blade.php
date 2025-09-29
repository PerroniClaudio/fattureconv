<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <h2 class="card-title">File</h2>
    <div class="overflow-x-auto">
      <table id="jobs-table" class="table w-full">
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
          <tr><td colspan="5" class="text-center">Caricamento...</td></tr>
        </tbody>
      </table>
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
        const endpoint = '/api/processed-files';
        const table = document.getElementById('jobs-table');

        function renderStatusBadge(status) {
          const s = (status || '').toLowerCase();
          const label = statusLabel(s);
          if (s === 'processed' || s === 'completed' || s === 'ai_completed' || s === 'completato') {
            return `<span class="badge badge-success">${escapeHtml(label)}</span>`;
          }
          if (s === 'error' || s === 'errore') {
            return `<span class="badge badge-error">${escapeHtml(label)}</span>`;
          }
          if (s === 'uploaded' || s === 'pending' || s === 'processing' || s === 'parsing_pdf' || s === 'calling_ai' || s === 'generating_word' || s === 'uploading_word') {
            return `<span class="badge badge-secondary">
                ${escapeHtml(label)} 
                <span class="loading loading-spinner loading-xs align-middle"></span>
              </span>`;
          }
          return `<span class="badge">${escapeHtml(label)}</span>`;
        }

        function statusLabel(status) {
          switch ((status || '').toLowerCase()) {
            case 'pending': return 'In coda';
            case 'uploaded': return 'Caricato';
            case 'parsing_pdf': return 'Estrazione PDF';
            case 'calling_ai': return 'Analisi dati';
            case 'ai_completed': return 'Analisi completata';
            case 'generating_word': return 'Generazione documento';
            case 'word_generated': return 'Documento generato';
            case 'uploading_word': return 'Upload documento';
            case 'completed': return 'Completato';
            case 'word_missing': return 'Documento mancante';
            case 'processing': return 'In elaborazione';
            case 'error': return 'Errore';
            default:
              // prettify unknown: replace underscores and capitalize
              return String(status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '—';
          }
        }

        function buildRow(row) {
          const date = row.created_at ? new Date(row.created_at).toLocaleString() : '';
          const file = row.original_filename || row.gcs_path || '—';
          const id = row.id;
          const status = row.status || '—';
          const hasWord = !!row.word_path;

          // costruisci lo stato con tooltip/alert in caso di errore
          let statusHtml = '';
          const s = (status || '').toLowerCase();
          if (s === 'error' || s === 'errore') {
            const msg = escapeHtml(row.error_message || 'Errore non disponibile');
            const structured = escapeHtml(JSON.stringify(row.structured_json || '', null, 2));
            const extracted = escapeHtml(row.extracted_text || '');
            const fileAttr = escapeHtml(row.original_filename || row.gcs_path || '');
            const createdAt = escapeHtml(row.created_at || '');
            const wordPath = escapeHtml(row.word_path || '');
            // title for tooltip, onclick to open detailed modal with data attributes
            statusHtml = `<button class="badge badge-error" title="${msg}" data-error="${msg}" data-structured='${structured}' data-extracted='${extracted}' data-file="${fileAttr}" data-created_at="${createdAt}" data-id="${id}" data-word_path="${wordPath}" onclick="showErrorElement(this)">${escapeHtml(status)}</button>`;
          } else {
            statusHtml = renderStatusBadge(escapeHtml(status));
          }

          return `
            <tr>
              <th>${id}</th>
              <td>${escapeHtml(file)}</td>
              <td>${statusHtml}</td>
              <td>${escapeHtml(date)}</td>
              <td>
                ${hasWord ? `<button class="btn btn-sm btn-primary" onclick="window.location='/processed-files/${id}/download'">Download</button>` : `<button class=\"btn btn-sm btn-ghost\" disabled>Non disponibile</button>`}
              </td>
            </tr>
          `;
        }

        function escapeHtml(unsafe) {
          if (unsafe === null || unsafe === undefined) return '';
          return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        async function fetchAndRender() {
          try {
            const res = await fetch(endpoint, {headers:{'Accept':'application/json'}});
            if (!res.ok) throw new Error('Network response was not ok');
            const data = await res.json();

            const tbody = table.querySelector('tbody');
            tbody.innerHTML = '';
            if (!Array.isArray(data) || data.length === 0) {
              tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nessun job trovato</td></tr>';
              return;
            }

            for (const row of data) {
              tbody.insertAdjacentHTML('beforeend', buildRow(row));
            }
          } catch (err) {
            const tbody = table.querySelector('tbody');
            tbody.innerHTML = `<tr><td colspan=\"5\" class=\"text-center text-error\">Errore caricamento: ${escapeHtml(err.message)}</td></tr>`;
          }
        }

        // mostra un alert grande con il messaggio di errore
        window.showError = function(message) {
          if (!message) message = 'Errore non disponibile';
          alert('Errore: ' + message);
        }

        // apri il modal dettagliato popolando i campi (riceve l'elemento cliccato)
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
            document.getElementById('modalMeta').innerText = `ID: ${id} • Creato: ${createdAt} • Word: ${wordPath}`;
            document.getElementById('modalErrorMessage').innerText = error;
            document.getElementById('modalStructuredJson').innerText = structured || '';
            document.getElementById('modalExtractedText').value = extracted || '';

            if (typeof dialog.showModal === 'function') {
              dialog.showModal();
            } else {
              // fallback semplice
              dialog.style.display = 'block';
            }
          } catch (e) {
            alert('Impossibile aprire il report dettagliato: ' + e.message);
          }
        }

        function copyToClipboard(text) {
          if (!text) return;
          navigator.clipboard.writeText(text).then(() => {
            alert('Copiato negli appunti');
          }).catch(() => {
            alert('Copia non riuscita');
          });
        }

        function downloadStructuredJson() {
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

        // Polling ogni 3 secondi
        fetchAndRender();
        const interval = setInterval(fetchAndRender, 3000);

        // se la pagina lascia il focus possiamo rallentare o fermare optional
        window.addEventListener('beforeunload', function(){ clearInterval(interval); });
      })();
    </script>
  </div>
</div>
