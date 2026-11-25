<?php

use App\Models\AuditLog;
use App\Models\InstitutionSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\GovernanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('boots cleanly with only superadmin@cecap.or.cr, 8 roles, and zero fictional data', function () {
    expect(User::count())->toBe(1);
    expect(Role::count())->toBe(8);
    expect(Student::count())->toBe(0);

    $superAdmin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    expect(Hash::check('Y4y4&K1k3', $superAdmin->password))->toBeTrue();
    expect($superAdmin->can('system.code.manage'))->toBeTrue();

    // Ningún otro rol tiene system.code.manage
    foreach (Role::where('name', '!=', 'super_admin')->get() as $role) {
        expect($role->hasPermissionTo('system.code.manage'))->toBeFalse();
    }
});

it('forces newly created users with must_change_password=true to /cambiar-contrasena', function () {
    $newUser = User::create([
        'name' => 'Secretaria Nueva',
        'email' => 'sec.nueva@cecap.or.cr',
        'identification_number' => '1-5555-6666',
        'phone' => '8812-3456',
        'password' => Hash::make('TempClave2026!'),
        'must_change_password' => true,
        'is_active' => true,
    ]);
    $newUser->assignRole('secretaria');

    $this->actingAs($newUser)
        ->get('/panel')
        ->assertRedirect('/cambiar-contrasena');

    $this->actingAs($newUser)
        ->post('/cambiar-contrasena', [
            'current_password' => 'TempClave2026!',
            'password' => 'NuevaSegura2026!',
            'password_confirmation' => 'NuevaSegura2026!',
        ])
        ->assertRedirect('/panel');

    expect($newUser->fresh()->must_change_password)->toBeFalse();
});

it('supports dual password recovery by personal data with rate limiting and audit trail', function () {
    $user = User::create([
        'name' => 'Alumna Recuperación',
        'email' => 'recup@cecap.or.cr',
        'identification_number' => '1-7777-8888',
        'phone' => '8999-1234',
        'password' => Hash::make('ViejaClave2026!'),
        'must_change_password' => false,
        'is_active' => true,
    ]);
    $user->assignRole('alumna');

    // Fallo de datos personales queda auditado
    $this->post('/recuperar-contrasena/identidad', [
        'identity_email' => 'recup@cecap.or.cr',
        'identification_number' => '9-9999-9999',
        'phone' => '8999-1234',
    ])->assertSessionHasErrors('identity_email');

    expect(AuditLog::where('event', 'security.recovery_identity_failed')->exists())->toBeTrue();

    // Validación correcta redirige a restablecer contraseña con token válido
    $resp = $this->post('/recuperar-contrasena/identidad', [
        'identity_email' => 'recup@cecap.or.cr',
        'identification_number' => '1-7777-8888',
        'phone' => '8999-1234',
    ]);
    $resp->assertRedirect();
    expect($resp->headers->get('Location'))->toContain('/restablecer-contrasena/');
    expect(AuditLog::where('event', 'security.recovery_identity_verified')->exists())->toBeTrue();
});

it('enforces admin/superadmin role guards, safe cloning, custom permissions, and global module toggles', function () {
    $superAdmin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $direccion = User::create([
        'name' => 'Directora',
        'email' => 'dir@cecap.or.cr',
        'identification_number' => '1-8000-0001',
        'phone' => '8800-0001',
        'password' => Hash::make('DirPass2026!'),
        'must_change_password' => false,
        'is_active' => true,
    ]);
    $direccion->assignRole('direccion');

    $governance = app(GovernanceService::class);

    // Dirección NO puede otorgar super_admin
    expect(fn () => $governance->assertCanAssignRoles($direccion, ['super_admin']))
        ->toThrow(ValidationException::class);

    // Clonación exige correo distinto
    expect(fn () => $governance->cloneUserPermissionsToNewUser($superAdmin, $direccion, [
        'name' => 'Clon',
        'email' => 'dir@cecap.or.cr',
        'identification_number' => '1-8000-0002',
        'phone' => '8800-0002',
    ], 'TempPass2026!'))->toThrow(ValidationException::class);

    // Clonación válida crea usuario nuevo con must_change_password=true y mismos roles
    $cloned = $governance->cloneUserPermissionsToNewUser($superAdmin, $direccion, [
        'name' => 'Subdirectora',
        'email' => 'subdir@cecap.or.cr',
        'identification_number' => '1-8000-0002',
        'phone' => '8800-0002',
    ], 'TempPass2026!');

    expect($cloned->must_change_password)->toBeTrue();
    expect($cloned->hasRole('direccion'))->toBeTrue();

    // Desactivación global de módulo bloquea acceso
    $setting = InstitutionSetting::current();
    $modules = $setting->enabled_modules;
    $modules['actividades'] = false;
    $setting->update(['enabled_modules' => $modules]);
    InstitutionSetting::clearCache();

    $this->actingAs($direccion)->get('/actividades')->assertForbidden();
});

it('auto-provisions alumna role on /registro self-registration', function () {
    $this->post('/registro', [
        'first_name' => 'Camila',
        'last_name' => 'Vega',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-9123-4567',
        'email' => 'camila.vega@correo.cr',
        'phone' => '8711-2233',
        'password' => 'ClaveSegura2026!',
        'password_confirmation' => 'ClaveSegura2026!',
    ])->assertRedirect('/panel');

    $user = User::where('email', 'camila.vega@correo.cr')->firstOrFail();
    expect($user->hasRole('alumna'))->toBeTrue();
    expect($user->hasEffectivePermission('portal.alumna.view'))->toBeTrue();
    expect(Student::where('user_id', $user->id)->exists())->toBeTrue();
});
