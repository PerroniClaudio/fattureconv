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
