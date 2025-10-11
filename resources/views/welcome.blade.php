@component('components.layout')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            @include('components.upload-form')
        </div>
        <div class="lg:col-span-2 space-y-6">
            @include('components.jobs-table')
        </div>
    </div>
@endcomponent
