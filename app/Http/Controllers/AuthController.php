<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $throttleKey = 'login:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Demasiados intentos de ingreso. Intente nuevamente en {$seconds} segundos.",
            ])->onlyInput('email');
        }

        $user = User::where('email', $email)->first();
        if ($user && ! $user->is_active) {
            return back()->withErrors([
                'email' => 'Esta cuenta está inactiva. Comuníquese con Secretaría o Dirección de CeCap.',
            ])->onlyInput('email');
        }

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 300);

            return back()->withErrors([
                'email' => 'Las credenciales ingresadas no son válidas.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $authUser = Auth::user();
        AuditService::log('auth.login', 'seguridad', $authUser);

        if ($authUser->must_change_password) {
            return redirect()->route('password.change')
                ->with('warning', 'Debe actualizar su contraseña temporal o inicial antes de usar la plataforma.');
        }

        return redirect()->intended(route('panel'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            AuditService::log('auth.logout', 'seguridad', $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sesión cerrada correctamente.');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validIdTypes = array_keys(\App\Models\Picklist::optionsFor('id_type'));

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'identification_type' => ['required', 'string', 'max:40', \Illuminate\Validation\Rule::in($validIdTypes)],
            'identification_number' => ['required', 'string', 'max:40', new \App\Rules\ValidIdentificationNumber($request->input('identification_type')), 'unique:users,identification_number'],
            'email' => ['required', 'email', 'max:255', new \App\Rules\ValidEmailDomain(), 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'identification_type.in' => 'Seleccione un tipo de identificación válido.',
        ]);

        $email = strtolower(trim($data['email']));
        $fullName = trim($data['first_name'].' '.$data['last_name'].' '.($data['second_last_name'] ?? ''));

        $user = DB::transaction(function () use ($data, $email, $fullName): User {
            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'identification_type' => $data['identification_type'],
                'identification_number' => trim($data['identification_number']),
                'phone' => trim($data['phone']),
                'gender' => 'femenino',
                'password' => Hash::make($data['password']),
                'must_change_password' => false,
                'password_changed_at' => now(),
                'uses_custom_permissions' => false,
                'is_active' => true,
            ]);

            // Auto-aprovisionamiento con rol estándar de Alumna del portal
            $user->assignRole('alumna');

            $existingStudent = Student::where('identification_number', trim($data['identification_number']))
                ->orWhere('email', $email)
                ->first();

            if ($existingStudent) {
                $existingStudent->update(['user_id' => $user->id]);
            } else {
                Student::create([
                    'user_id' => $user->id,
                    'student_code' => Student::nextCode(),
                    'first_name' => trim($data['first_name']),
                    'last_name' => trim($data['last_name']),
                    'second_last_name' => ! empty($data['second_last_name']) ? trim($data['second_last_name']) : null,
                    'identification_type' => $data['identification_type'],
                    'identification_number' => trim($data['identification_number']),
                    'email' => $email,
                    'phone' => trim($data['phone']),
                    'gender' => 'femenino',
                    'status' => 'activo',
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        AuditService::log('auth.alumna_registered', 'seguridad', $user, null, ['role' => 'alumna']);

        return redirect()->route('panel')->with('status', 'Bienvenida al Portal de Alumnas CeCap SURÍ.');
    }

    public function showChangePassword(): View
    {
        return view('auth.change-password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::min(8)],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->string('password')->toString()),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        AuditService::log('security.password_changed', 'seguridad', $user);

        return redirect()->route('panel')->with('status', 'Contraseña actualizada con éxito.');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $email = strtolower(trim($request->input('email')));
        $key = 'pw-email:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['email' => "Límite alcanzado. Intente de nuevo en {$seconds} segundos."]);
        }
        RateLimiter::hit($key, 900);

        $user = User::where('email', $email)->where('is_active', true)->first();
        if ($user) {
            $token = Password::broker()->createToken($user);
            AuditService::log('security.recovery_email_requested', 'seguridad', $user, null, ['method' => 'email_link'], null, $user->id, $user->name);
            // En entorno local/log se registra el enlace y se muestra confirmación sin filtrar usuarios inexistentes
            logger()->info('CeCap enlace de recuperación de contraseña', [
                'email' => $email,
                'url' => route('password.reset', ['token' => $token, 'email' => $email]),
            ]);
        }

        return back()->with('status', 'Si el correo existe en nuestros registros, enviamos un enlace seguro para restablecer su contraseña.');
    }

    /**
     * Recuperación dual opción 2: Validación de datos personales
     * (correo + cédula/DIMEX + teléfono) con RateLimiter y auditoría.
     */
    public function verifyIdentityRecovery(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identity_email' => ['required', 'email'],
            'identification_number' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $email = strtolower(trim($data['identity_email']));
        $rateKey = 'pw-identity:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            AuditService::log('security.recovery_identity_rate_limited', 'seguridad', null, null, ['email' => $email]);

            return back()->withErrors([
                'identity_email' => "Demasiados intentos de validación personal. Intente nuevamente en {$seconds} segundos.",
            ])->withInput();
        }

        $normalize = static fn (?string $val): string => preg_replace('/[^0-9A-Za-z]/', '', strtoupper((string) $val));

        $user = User::where('email', $email)->where('is_active', true)->first();
        $idMatches = $user && $normalize($user->identification_number) !== '' && $normalize($user->identification_number) === $normalize($data['identification_number']);
        $phoneMatches = $user && $normalize($user->phone) !== '' && str_ends_with($normalize($user->phone), substr($normalize($data['phone']), -8));

        if (! $user || ! $idMatches || ! $phoneMatches) {
            RateLimiter::hit($rateKey, 900);
            AuditService::log(
                event: 'security.recovery_identity_failed',
                module: 'seguridad',
                auditable: $user,
                newValues: ['email' => $email],
                reason: 'Datos personales no coinciden'
            );

            return back()->withErrors([
                'identity_email' => 'Los datos personales ingresados no coinciden con nuestros registros institucionales.',
            ])->withInput();
        }

        RateLimiter::clear($rateKey);
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        AuditService::log(
            event: 'security.recovery_identity_verified',
            module: 'seguridad',
            auditable: $user,
            newValues: ['method' => 'personal_data_validation'],
            userId: $user->id,
            actorName: $user->name
        );

        return redirect()->route('password.reset', ['token' => $token, 'email' => $user->email])
            ->with('status', 'Identidad verificada correctamente. Defina su nueva contraseña.');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $email = strtolower(trim($data['email']));
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($data['token'], $record->token) || now()->diffInMinutes($record->created_at) > 60) {
            return back()->withErrors(['email' => 'El enlace o token de recuperación expiró o no es válido.']);
        }

        $user = User::where('email', $email)->where('is_active', true)->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No se encontró una cuenta activa para este correo.']);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        AuditService::log('security.password_reset_completed', 'seguridad', $user, null, null, null, $user->id, $user->name);

        return redirect()->route('login')->with('status', 'Contraseña restablecida correctamente. Ya puede iniciar sesión.');
    }
}
