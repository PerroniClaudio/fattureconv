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
          @if(isset($processedFiles) && $processedFiles->count())
            @foreach($processedFiles as $row)
              @php
                $status = strtolower($row->status ?? '');
                $labels = [
                  'pending' => 'In coda',
                  'uploaded' => 'Caricato',
                  'parsing_pdf' => 'Estrazione PDF',
                  'calling_ai' => 'Analisi dati',
                  'ai_completed' => 'Analisi completata',
                  'generating_word' => 'Generazione documento',
                  'word_generated' => 'Documento generato',
                  'uploading_word' => 'Upload documento',
                  'completed' => 'Completato',
                  'word_missing' => 'Documento mancante',
                  'processing' => 'In elaborazione',
                  'error' => 'Errore',
                ];
                $label = $labels[$status] ?? (\Illuminate\Support\Str::title(str_replace('_', ' ', $row->status ?? '—')));
                $badgeClass = 'badge';
                if (in_array($status, ['processed', 'completed', 'ai_completed', 'completato'])) {
                  $badgeClass = 'badge badge-success';
                } elseif (in_array($status, ['error', 'errore'])) {
                  $badgeClass = 'badge badge-error';
                } elseif (in_array($status, ['uploaded','pending','processing','parsing_pdf','calling_ai','generating_word','uploading_word'])) {
                  $badgeClass = 'badge badge-secondary';
                }
                $date = $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '';
                $fileName = $row->original_filename ?? $row->gcs_path ?? '—';
              @endphp
              <tr data-id="{{ $row->id }}">
                <th>{{ $row->id }}</th>
                <td>{{ $fileName }}</td>
                <td class="status-cell">
                  @if(in_array($status, ['error','errore']))
                    <button class="badge badge-error" title="{{ $row->error_message ?? 'Errore non disponibile' }}" 
                      data-error="{{ e($row->error_message ?? '') }}" 
                      data-structured='{{ e(json_encode($row->structured_json ?? '')) }}' 
                      data-extracted='{{ e($row->extracted_text ?? '') }}' 
                      data-file="{{ e($fileName) }}" 
                      data-created_at="{{ e($row->created_at) }}" 
                      data-id="{{ $row->id }}" 
                      data-word_path="{{ e($row->word_path ?? '') }}"
                      onclick="showErrorElement(this)">
                      {{ $label }}
                    </button>
                  @else
                    <span class="{{ $badgeClass }}">
                      @if(in_array($status, ['uploaded','pending','processing','parsing_pdf','calling_ai','generating_word','uploading_word']))
                        {{ $label }} <span class="loading loading-spinner loading-xs align-middle"></span>
                      @else
                        {{ $label }}
                      @endif
                    </span>
                  @endif
                </td>
                <td>{{ $date }}</td>
                <td class="actions-cell">
                  @if($row->word_path)
                    <a class="btn btn-sm btn-primary" href="{{ url('/processed-files/'.$row->id.'/download') }}" data-download-url="{{ url('/processed-files/'.$row->id.'/download') }}">Download</a>
                  @else
                    <button class="btn btn-sm btn-ghost" disabled>Non disponibile</button>
                  @endif
                </td>
              </tr>
            @endforeach
          @else
            <tr><td colspan="5" class="text-center">Nessun job trovato</td></tr>
          @endif
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      @if(isset($processedFiles))
        {{ $processedFiles->appends(request()->query())->links() }}
      @endif
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
        const statusEndpoint = '/api/processed-files/statuses'; // POST { ids: [..] }
        const table = document.getElementById('jobs-table');

        function escapeHtml(unsafe) {
          if (unsafe === null || unsafe === undefined) return '';
          return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        // aggiorna una riga dato l'id e i dati di risposta
        function updateRow(id, data) {
          const tr = table.querySelector(`tr[data-id="${id}"]`);
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

        // ottieni gli id visibili nella tabella
        function getVisibleIds() {
          const ids = [];
          table.querySelectorAll('tbody tr[data-id]').forEach(tr => ids.push(tr.getAttribute('data-id')));
          return ids;
        }

        async function pollStatuses() {
          try {
            const ids = getVisibleIds();
            if (!ids.length) return;

            const tokenEl = document.querySelector('meta[name="csrf-token"]');
            const csrf = tokenEl ? tokenEl.getAttribute('content') : null;
            const res = await fetch(statusEndpoint, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? {'X-CSRF-TOKEN': csrf} : {}) },
              body: JSON.stringify({ ids })
            });

            if (!res.ok) throw new Error('Network response was not ok');
            const payload = await res.json();

            // payload expected: { "<id>": { ... }, ... } or array of objects with id property
            if (Array.isArray(payload)) {
              for (const item of payload) {
                if (item && item.id !== undefined) updateRow(item.id, item);
              }
            } else if (payload && typeof payload === 'object') {
              for (const [id, data] of Object.entries(payload)) {
                updateRow(id, data || {});
              }
            }
          } catch (e) {
            console.warn('Polling error:', e.message || e);
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
            document.getElementById('modalMeta').innerText = `ID: ${id} • Creato: ${createdAt} • Word: ${wordPath}`;
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

        // Start polling every 3 seconds
        pollStatuses();
        const interval = setInterval(pollStatuses, 3000);
        window.addEventListener('beforeunload', function(){ clearInterval(interval); });
      })();
    </script>
  </div>
</div>
