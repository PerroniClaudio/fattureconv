<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <h2 class="card-title">Carica PDF</h2>
    <p>Carica i tuoi file PDF per la conversione.</p>
    <form id="upload-form" class="space-y-4" action="{{ url('/upload') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div>
        <input type="file" name="pdf" accept="application/pdf" id="pdf-file" class="file-input file-input-bordered w-full" required />
      </div>
      <div class="flex items-center gap-2">
        <button class="btn btn-primary" type="submit">Carica</button>
        <button class="btn btn-ghost" type="reset">Reset</button>
      </div>
    </form>
  </div>
</div>
