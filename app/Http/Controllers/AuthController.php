<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleIdentityService;
use App\Services\SubscriptionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request, SubscriptionService $subscriptions)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::create($data + ['role' => 'etudiant']);
        event(new Registered($user));
        $subscriptions->ensureFreeSubscription($user);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('web')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Identifiants invalides.']);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('web')->plainTextToken,
        ]);
    }

    public function google(Request $request, GoogleIdentityService $google, SubscriptionService $subscriptions)
    {
        $data = $request->validate([
            'credential' => ['required', 'string', 'max:4096'],
        ]);
        $identity = $google->verify($data['credential']);

        $user = DB::transaction(function () use ($identity) {
            $user = User::where('google_id', $identity['sub'])
                ->orWhere('email', $identity['email'])
                ->lockForUpdate()
                ->first();

            if ($user && $user->google_id && ! hash_equals($user->google_id, $identity['sub'])) {
                throw ValidationException::withMessages([
                    'credential' => 'Cette adresse e-mail est déjà associée à un autre compte Google.',
                ]);
            }

            if (! $user) {
                $user = new User([
                    'name' => $identity['name'],
                    'email' => $identity['email'],
                    'password' => Str::random(64),
                    'role' => 'etudiant',
                ]);
                $user->forceFill([
                    'google_id' => $identity['sub'],
                    'google_avatar_url' => $identity['picture'],
                    'email_verified_at' => now(),
                ])->save();
            } elseif (! $user->google_id) {
                $user->forceFill([
                    'google_id' => $identity['sub'],
                    'google_avatar_url' => $identity['picture'],
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            } elseif ($identity['picture']) {
                $user->forceFill(['google_avatar_url' => $identity['picture']])->save();
            }

            return $user;
        });

        $subscriptions->ensureFreeSubscription($user);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('web-google')->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();
        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Si cette adresse est associée à un compte, un lien de réinitialisation a été envoyé.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé.']);
    }
}
