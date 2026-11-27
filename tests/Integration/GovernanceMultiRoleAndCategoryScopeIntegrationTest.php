<?php

use App\Livewire\AcademicPage;
use App\Livewire\DirectionPage;
use App\Models\AcademicCategory;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('integrates quick category creation, multi-role user provisioning (profesora + alumna + coordinador_categoria), mandatory password change, and category scoping', function () {
    $superAdmin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

    // Crear categoría inline desde DirectionPage y crear usuario multirrol
    Livewire::actingAs($superAdmin)
        ->test(DirectionPage::class)
        ->set('tab', 'users')
        ->set('quick_cat_code', 'CAT-MODA')
        ->set('quick_cat_name', 'Corte y Confección')
        ->call('createQuickCategory')
        ->assertHasNoErrors();

    $catModa = AcademicCategory::where('code', 'CAT-MODA')->firstOrFail();
    $catOther = AcademicCategory::create(['code' => 'CAT-OTRA', 'name' => 'Otra Categoría', 'created_by' => $superAdmin->id]);

    // Sin registro previo en Colaboradores, crear usuario con roles distintos a super_admin debe bloquearse
    Livewire::actingAs($superAdmin)
        ->test(DirectionPage::class)
        ->set('new_user_name', 'Rebeca Morales')
        ->set('new_user_email', 'rebeca.morales@cecap.or.cr')
        ->set('new_user_id_num', '1-5511-2233')
        ->set('new_user_phone', '8844-5566')
        ->set('new_user_password', 'TempInicial2026!')
        ->set('new_user_roles', ['profesora', 'alumna', 'coordinador_categoria'])
        ->set('new_user_categories', [$catModa->id])
        ->call('createUser')
        ->assertHasErrors('new_user_id_num');

    $staffRebeca = StaffMember::create([
        'staff_code' => StaffMember::nextCode(),
        'staff_role_type' => 'both',
        'first_name' => 'Rebeca',
        'last_name' => 'Morales',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-5511-2233',
        'email' => 'rebeca.morales@cecap.or.cr',
        'phone' => '8844-5566',
        'birth_date' => '1988-05-14',
        'status' => 'activo',
        'created_by' => $superAdmin->id,
    ]);

    Livewire::actingAs($superAdmin)
        ->test(DirectionPage::class)
        ->set('new_user_staff_id', $staffRebeca->id)
        ->set('new_user_password', 'TempInicial2026!')
        ->set('new_user_roles', ['profesora', 'alumna', 'coordinador_categoria'])
        ->set('new_user_categories', [$catModa->id])
        ->call('createUser')
        ->assertHasNoErrors();

    $multiUser = User::where('email', 'rebeca.morales@cecap.or.cr')->firstOrFail();
    expect($multiUser->must_change_password)->toBeTrue();
    expect($multiUser->activeAcademicCategoryIds())->toBe([$catModa->id]);
    expect($staffRebeca->fresh()->user_id)->toBe($multiUser->id);

    // Enlace de expediente estudiantil para comprobar portal multirrol
    Student::create([
        'user_id' => $multiUser->id,
        'student_code' => Student::nextCode(),
        'first_name' => 'Rebeca',
        'last_name' => 'Morales',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-5511-2233',
        'email' => 'rebeca.morales@cecap.or.cr',
        'phone' => '8844-5566',
        'gender' => 'femenino',
    ]);

    // Bloqueo hasta cambio obligatorio de contraseña
    $this->actingAs($multiUser)->get('/academico')->assertRedirect('/cambiar-contrasena');

    $this->actingAs($multiUser)
        ->post('/cambiar-contrasena', [
            'current_password' => 'TempInicial2026!',
            'password' => 'MiClaveDefinitiva2026!',
            'password_confirmation' => 'MiClaveDefinitiva2026!',
        ])
        ->assertRedirect('/panel');

    // En /panel visualiza su Portal de Alumna simultáneamente con su perfil docente
    $this->actingAs($multiUser->fresh())
        ->get('/panel')
        ->assertOk()
        ->assertSee('Mi Portal de Alumna');

    // En /academico puede crear curso en CAT-MODA, pero es bloqueada en CAT-OTRA
    Livewire::actingAs($multiUser->fresh())
        ->test(AcademicPage::class)
        ->set('course_category_id', $catModa->id)
        ->set('course_code', 'MODA-01')
        ->set('course_name', 'Patronaje Básico')
        ->set('course_program_type', 'curso_corto')
        ->call('createCourse')
        ->assertHasNoErrors();

    Livewire::actingAs($multiUser->fresh())
        ->test(AcademicPage::class)
        ->set('course_category_id', $catOther->id)
        ->set('course_code', 'OTRA-01')
        ->set('course_name', 'Curso Bloqueado')
        ->set('course_program_type', 'curso_corto')
        ->call('createCourse')
        ->assertHasErrors('course_category_id');
});
