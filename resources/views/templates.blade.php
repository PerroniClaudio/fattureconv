@component('components.layout')
    <div class="max-w-2xl space-y-6">
        <div class="card bg-base-100 shadow">
            <div class="card-body space-y-4">
                <h2 class="card-title">Carica template fatture</h2>
                <p class="text-sm opacity-80">
                    Carica un file .docx per aggiornare il template usato nella generazione delle fatture.
                    Il file verrà salvato in <code class="font-mono text-xs">storage/app/templates/ift_template_fatture.docx</code>.
                </p>

                <form action="{{ route('templates.upload') }}" method="post" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="file" name="template" accept=".docx" class="file-input file-input-bordered w-full" required />
                    <button type="submit" class="btn btn-primary">Carica template</button>
                </form>
            </div>
        </div>
    </div>
@endcomponent
