@component('components.layout')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Errori</h1>
                <p class="text-sm opacity-70">ProcessedFile con errori di elaborazione.</p>
            </div>
        </div>

        <div class="bg-base-100 rounded-box shadow p-4">
            <div class="overflow-x-auto">
                <table id="errors-table" class="table w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>File</th>
                            <th>Stato</th>
                            <th>Data</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="errors-tbody">
                        {{-- populated by client-side JS --}}
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div>
                    <button id="errors-prev" class="btn btn-sm btn-outline" disabled>
                        <x-lucide-arrow-left class="inline-block w-4 h-4 mr-1" />
                    </button>
                    <button id="errors-next" class="btn btn-sm btn-outline ml-2" disabled>
                        <x-lucide-arrow-right class="inline-block w-4 h-4 mr-1" />
                    </button>
                </div>
                <div class="text-sm text-muted" id="errors-pagination-info"></div>
            </div>
        </div>

        @include('components.jobs-table.error-modal')
        @include('components.jobs-table.delete-confirmation-modal')
    </div>
@endcomponent
