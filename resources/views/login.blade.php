@component('components.layout')
    <main class="flex flex-col items-center justify-center gap-1 h-[70vh]">
        <div class="card card-dash bg-base-200 w-96">
            <div class="card-body">
                <h2 class="card-title">Accedi</h2>
                <p>L'accesso è riservato ai membri del tenant Office 365</p>
                <div class="card-actions justify-end">
                    <a href="{{ route('auth.microsoft') }}"><button class="btn btn-primary">Accedi</button></a>
                </div>
            </div>
        </div>
    </main>
@endcomponent