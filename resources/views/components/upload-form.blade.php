<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <h2 class="card-title">Carica PDF</h2>
    <p>Carica i tuoi file PDF per la conversione.</p>
    <form id="upload-form" class="space-y-4" onsubmit="event.preventDefault(); alert('Upload simulato');">
      <div>
        <input type="file" accept="application/pdf" id="pdf-file" class="file-input file-input-bordered w-full" />
      </div>
      <div class="flex items-center gap-2">
        <button class="btn btn-primary" type="submit">Carica</button>
        <button class="btn btn-ghost" type="button" onclick="document.getElementById('pdf-file').value=null">Reset</button>
      </div>
    </form>
  </div>
</div>
