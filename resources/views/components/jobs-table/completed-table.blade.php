<div id="tab-completed" class="tab-panel hidden">
  <div class="overflow-x-auto">
    <table id="completed-table" class="table w-full">
      <thead>
        <tr>
          <th>#</th>
          <th>File</th>
          <th>Stato</th>
          <th>Data</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody id="completed-tbody">
        {{-- populated by client-side JS --}}
      </tbody>
    </table>
  </div>

  <div class="mt-4 flex items-center justify-between">
    <div>
      <button id="completed-prev" class="btn btn-sm btn-outline" disabled>
        <x-lucide-arrow-left class="inline-block w-4 h-4 mr-1" />
      </button>
      <button id="completed-next" class="btn btn-sm btn-outline ml-2" disabled>
        <x-lucide-arrow-right class="inline-block w-4 h-4 mr-1" />
      </button>
    </div>
    <div class="text-sm text-muted" id="completed-pagination-info"></div>
  </div>
</div>
