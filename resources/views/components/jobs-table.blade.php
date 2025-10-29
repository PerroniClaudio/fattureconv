<div class="card bg-base-100 shadow-md">
    <div class="card-body">
        <h2 class="card-title">File</h2>

        <x-jobs-table.tabs />

        @php
            $items = isset($processedFiles) ? collect($processedFiles->items()) : collect([]);
            $inProgressStatuses = [
                'pending',
                'uploaded',
                'processing',
                'parsing_pdf',
                'calling_ai',
                'generating_word',
                'uploading_word',
            ];
            $completedStatuses = ['ai_completed', 'word_generated', 'completed', 'processed', 'word_missing', 'merged'];
            $inProgress = $items->filter(function ($r) use ($inProgressStatuses) {
                return in_array(strtolower($r->status ?? ''), $inProgressStatuses);
            });
            $completed = $items->filter(function ($r) use ($completedStatuses) {
                return in_array(strtolower($r->status ?? ''), $completedStatuses);
            });
        @endphp

        <x-jobs-table.in-progress-table />
        <x-jobs-table.completed-table />
        <x-jobs-table.error-modal />
        <x-jobs-table.delete-confirmation-modal />

    </div>
</div>
