<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <h2 class="card-title">Carica PDF</h2>
    <p>Carica i tuoi file PDF per la conversione.</p>
    <form id="upload-form" class="space-y-4" action="{{ url('/upload') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div>
        <!-- Allow multiple PDF files to be selected -->
        <input type="file" name="pdf[]" accept="application/pdf" id="pdf-file" class="" multiple required />
        <!-- FilePond will upload files async; we collect processed IDs here -->
        <div id="processed-ids-container"></div>
        <input type="hidden" name="processed_ids" id="processed-ids" />
      </div>
    </form>
  </div>
</div>
