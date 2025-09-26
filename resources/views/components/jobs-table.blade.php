<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <h2 class="card-title">Jobs completati</h2>
    <div class="overflow-x-auto">
      <table class="table w-full">
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
          @php
            $mock = [
              ['id'=>1,'file'=>'fattura-2025-001.pdf','status'=>'Completato','date'=>'2025-09-26','out'=>'fattura-2025-001.csv'],
              ['id'=>2,'file'=>'fattura-2025-002.pdf','status'=>'Completato','date'=>'2025-09-25','out'=>'fattura-2025-002.csv'],
              ['id'=>3,'file'=>'fattura-2025-003.pdf','status'=>'Errore','date'=>'2025-09-24','out'=>null],
            ];
          @endphp
          @foreach($mock as $row)
            <tr>
              <th>{{ $row['id'] }}</th>
              <td>{{ $row['file'] }}</td>
              <td>
                @if($row['status']==='Completato')
                  <span class="badge badge-success">{{ $row['status'] }}</span>
                @elseif($row['status']==='Errore')
                  <span class="badge badge-error">{{ $row['status'] }}</span>
                @else
                  <span class="badge">{{ $row['status'] }}</span>
                @endif
              </td>
              <td>{{ $row['date'] }}</td>
              <td>
                @if($row['out'])
                  <button class="btn btn-sm btn-primary" onclick="alert('Download simulato: {{ $row['out'] }}')">
                    <x-lucide-download class="inline-block text-primary-content w-4 h-4" /> Download
                  </button>
                @else
                  <button class="btn btn-sm btn-ghost" disabled>
                    <x-lucide-download class="inline-block" />
                    <span class="ml-2">N/A</span>
                  </button>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
