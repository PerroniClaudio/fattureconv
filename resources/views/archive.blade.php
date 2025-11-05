@component('components.layout')
    @vite('resources/js/archive.js')
    <section class="card bg-base-100 shadow-md">
        <div class="card-body">
            <div class="grid grid-cols-6">
                <div class="bg-base-300 rounded-tl-md rounded-bl-md">
                    <ul class="list rounded-box" id="archive-year-list">
                        @php
                            $currentYear = now()->year;
                        @endphp
                        @for ($year = $currentYear; $year >= $currentYear - 3; $year--)
                            <li class="list-row" data-year="{{ $year }}">
                                <button type="button" class="btn btn-ghost btn-sm justify-start w-full" data-year-trigger>
                                    {{ $year }}
                                </button>
                            </li>
                        @endfor
                    </ul>
                </div>
                <div class="bg-base-200">
                    <ul class="list rounded-box" id="archive-month-list">
                        @for ($month = 1; $month <= 12; $month++)
                            <li class="list-row" data-month="{{ $month }}">
                                <button type="button" class="btn btn-ghost btn-sm justify-start w-full" data-month-trigger>
                                    {{ ucfirst(\Carbon\Carbon::create()->locale('it')->month($month)->translatedFormat('F')) }}
                                </button>
                            </li>
                        @endfor

                    </ul>
                </div>
                <div class="col-span-4 bg-base-100">
                    <x-archive-list />
                </div>
            </div>
        </div>
    </section>
@endcomponent
