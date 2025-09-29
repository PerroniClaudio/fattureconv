<?php
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Log;

Route::get('/auth/redirect', function () {
    return Socialite::driver('microsoft')->redirect();
})->name('auth.microsoft');

Route::get('/login', function () {
    return Socialite::driver('microsoft')->redirect();
})->name('login');

Route::get('/auth/microsoft/callback', function () {
    try {
        // Verifica se c'è uno stato nella richiesta
        if (!request()->has('state') || !session()->has('state')) {
            return redirect()->route('login')->withErrors(['error' => 'Stato di autenticazione non valido. Riprova.']);
        }

        $user = Socialite::driver('microsoft')->user();

        $existingUser = User::where('email', $user->getEmail())->first();

        if ($existingUser) {
            Auth::login($existingUser);
        } else {
            $newUser = User::create([
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'password' => encrypt($user->getEmail()),
                'provider_id' => $user->getId(),
                'provider' => 'microsoft',
            ]);

            Auth::login($newUser);
        }

        return redirect()->intended('/app');
    } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
        // Log l'errore per debugging
        Log::error('Socialite InvalidStateException: ' . $e->getMessage(), [
            'request_data' => request()->all(),
            'session_id' => session()->getId(),
            'has_state_in_request' => request()->has('state'),
            'has_state_in_session' => session()->has('state'),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('login')->withErrors(['error' => 'Errore di autenticazione. Riprova.']);
    } catch (\Exception $e) {
        Log::error('Socialite general exception: ' . $e->getMessage());

        return redirect()->route('login')->withErrors(['error' => 'Errore durante l\'autenticazione. Riprova.']);
    }
});