@component('components.layout')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            @include('components.upload-form')
        </div>
        <div class="lg:col-span-2 space-y-6">
            @include('components.jobs-table')
            <div class="card bg-base-100 shadow-md p-6">
                <h3 class="text-lg font-semibold">Informazioni</h3>
                <p class="text-sm text-muted">Questa è una demo. I pulsanti di upload e download sono simulati.</p>
            </div>
        </div>
    </div>
@endcomponent
