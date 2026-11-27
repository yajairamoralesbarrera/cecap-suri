<?php

namespace App\Livewire;

use App\Models\AcademicCategory;
use App\Models\AuditLog;
use App\Models\CrProvince;
use App\Models\Enrollment;
use App\Models\FeeCharge;
use App\Models\GraduationRecord;
use App\Models\InstitutionSetting;
use App\Models\PaymentReceipt;
use App\Models\Picklist;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GovernanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

#[Layout('layouts.app')]
#[Title('Dirección y Gobernanza')]
class DirectionPage extends Component
{
    public string $tab = 'kpis';

    public ?string $feedback = null;

    public ?string $issuedTempPassword = null;

    // Tab 2: Crear Usuario Nuevo (siempre must_change_password = true)
    public ?int $new_user_staff_id = null;

    public string $new_user_name = '';

    public string $new_user_email = '';

    public string $new_user_id_num = '';

    public string $new_user_phone = '';

    public string $new_user_password = '';

    public array $new_user_roles = ['secretaria'];

    public array $new_user_categories = [];

    // Edición de accesos por persona
    public ?int $editingUserId = null;

    public array $edit_roles = [];

    public bool $edit_uses_custom = false;

    public array $edit_direct_permissions = [];

    public array $edit_categories = [];

    // Creación rápida de categoría desde Gobernanza si el catálogo está vacío
    public string $quick_cat_code = '';

    public string $quick_cat_name = '';

    // Clonación segura de permisos (exige datos personales nuevos)
    public ?int $clone_source_user_id = null;

    public ?int $clone_staff_id = null;

    public string $clone_name = '';

    public string $clone_email = '';

    public string $clone_id_num = '';

    public string $clone_phone = '';

    public string $clone_temp_password = '';

    // Tab 3: Filtro Auditoría
    public string $audit_module_filter = '';

    // Tab 4: Picklists CR
    public string $pick_group = 'education_level';

    public string $pick_code = '';

    public string $pick_label = '';

    // Tab 5: Configuración Institucional y Módulos Globales
    public string $set_institution_name = '';

    public string $set_short_name = '';

    public string $set_phone = '';

    public string $set_email = '';

    public string $set_address = '';

    public int $set_provisional_days = 7;

    public string $set_online_notice = '';

    public string $set_attention_hours = '';

    public string $set_gender_policy_message = '';

    public bool $set_require_solvency = true;

    public array $set_enabled_modules = [];

    public string $set_director_name = '';

    public string $set_director_title = '';

    public string $set_receipt_prefix = 'REC';

    public function mount(): void
    {
        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        $s = InstitutionSetting::current();
        $this->set_institution_name = (string) $s->institution_name;
        $this->set_short_name = (string) $s->short_name;
        $this->set_phone = (string) $s->phone;
        $this->set_email = (string) $s->email;
        $this->set_address = (string) ($s->address ?? '');
        $this->set_provisional_days = (int) $s->online_enrollment_provisional_days;
        $this->set_online_notice = (string) $s->online_enrollment_notice;
        $this->set_attention_hours = (string) $s->attention_hours;
        $this->set_gender_policy_message = (string) $s->gender_policy_message;
        $this->set_require_solvency = (bool) $s->require_financial_solvency_for_graduation;
        $this->set_enabled_modules = $s->enabled_modules ?? array_fill_keys(array_keys(config('cecap.modules')), true);
        $this->set_director_name = (string) $s->director_name;
        $this->set_director_title = (string) $s->director_title;
        $this->set_receipt_prefix = (string) $s->receipt_prefix;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->issuedTempPassword = null;
        $this->resetValidation();
    }

    public function updatedNewUserStaffId($value): void
    {
        if (! $value) {
            return;
        }

        $staff = StaffMember::find((int) $value);
        if (! $staff) {
            return;
        }

        $this->new_user_name = $staff->full_name;
        $this->new_user_email = (string) $staff->email;
        $this->new_user_id_num = (string) $staff->identification_number;
        $this->new_user_phone = (string) ($staff->phone ?? '');
    }

    public function updatedCloneStaffId($value): void
    {
        if (! $value) {
            return;
        }

        $staff = StaffMember::find((int) $value);
        if (! $staff) {
            return;
        }

        $this->clone_name = $staff->full_name;
        $this->clone_email = (string) $staff->email;
        $this->clone_id_num = (string) $staff->identification_number;
        $this->clone_phone = (string) ($staff->phone ?? '');
    }

    public function createUser(GovernanceService $governance): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.users.manage'), 403);

        $this->validate([
            'new_user_name' => ['required', 'string', 'max:140'],
            'new_user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'new_user_id_num' => ['required', 'string', 'max:40', 'unique:users,identification_number'],
            'new_user_phone' => ['required', 'string', 'max:30'],
            'new_user_password' => ['required', 'string', 'min:8'],
            'new_user_roles' => ['required', 'array', 'min:1'],
        ]);

        try {
            $governance->assertCanAssignRoles(Auth::user(), $this->new_user_roles);
            $collaborator = $governance->assertCollaboratorRegistered(
                targetRoles: $this->new_user_roles,
                identificationNumber: $this->new_user_id_num,
                email: $this->new_user_email,
                errorKey: 'new_user_id_num'
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $targetKey = $key === 'roles' ? 'new_user_roles' : $key;
                $this->addError($targetKey, $messages[0]);
            }

            return;
        }

        $user = User::create([
            'name' => trim($this->new_user_name),
            'email' => strtolower(trim($this->new_user_email)),
            'identification_type' => $collaborator?->identification_type ?? 'cedula_nacional',
            'identification_number' => trim($this->new_user_id_num),
            'phone' => trim($this->new_user_phone),
            'gender' => 'femenino',
            'password' => Hash::make($this->new_user_password),
            'must_change_password' => true, // Todo usuario nuevo exige cambio obligatorio
            'uses_custom_permissions' => false,
            'is_active' => true,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if ($collaborator && ! $collaborator->user_id) {
            $collaborator->update(['user_id' => $user->id]);
        }

        $user->syncRoles($this->new_user_roles);

        if (in_array('coordinador_categoria', $this->new_user_roles, true) && ! empty($this->new_user_categories)) {
            $sync = [];
            foreach ($this->new_user_categories as $cid) {
                $sync[(int) $cid] = ['is_active' => true, 'assigned_by' => Auth::id()];
            }
            $user->academicCategories()->sync($sync);
        }

        AuditService::log('governance.user_created', 'direccion', $user, null, ['email' => $user->email, 'roles' => $this->new_user_roles, 'must_change_password' => true]);
        $this->reset(['new_user_staff_id', 'new_user_name', 'new_user_email', 'new_user_id_num', 'new_user_phone', 'new_user_password', 'new_user_categories']);
        $this->feedback = 'Usuario creado con cambio obligatorio de contraseña en su primer ingreso.';
    }

    public function createQuickCategory(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.users.manage'), 403);

        $this->validate([
            'quick_cat_code' => ['required', 'string', 'max:40', 'unique:academic_categories,code'],
            'quick_cat_name' => ['required', 'string', 'max:140'],
        ]);

        $cat = AcademicCategory::create([
            'code' => strtoupper(trim($this->quick_cat_code)),
            'name' => trim($this->quick_cat_name),
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        AuditService::log('academic.category_created', 'academico', $cat, null, $cat->only(['code', 'name']));

        if ($this->editingUserId) {
            $this->edit_categories[] = $cat->id;
        } else {
            $this->new_user_categories[] = $cat->id;
        }

        $this->reset(['quick_cat_code', 'quick_cat_name']);
        $this->feedback = sprintf('Categoría "%s" creada y preseleccionada para asignar.', $cat->name);
    }

    public function editUserAccess(int $userId): void
    {
        $u = User::findOrFail($userId);
        $this->editingUserId = $u->id;
        $this->edit_roles = $u->getRoleNames()->all();
        $this->edit_uses_custom = (bool) $u->uses_custom_permissions;
        $this->edit_direct_permissions = $u->uses_custom_permissions
            ? $u->getDirectPermissions()->pluck('name')->all()
            : $u->getAllPermissions()->pluck('name')->all();
        $this->edit_categories = $u->activeAcademicCategoryIds();
    }

    public function cancelEditUserAccess(): void
    {
        $this->editingUserId = null;
    }

    public function saveUserAccess(GovernanceService $governance): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.users.manage'), 403);
        if (! $this->editingUserId) {
            return;
        }

        $target = User::findOrFail($this->editingUserId);

        try {
            $governance->assertCanAssignRoles(Auth::user(), $this->edit_roles, $target);
            $collaborator = $governance->assertCollaboratorRegistered(
                targetRoles: $this->edit_roles,
                identificationNumber: $target->identification_number,
                email: $target->email,
                userId: $target->id,
                errorKey: 'edit_roles'
            );
        } catch (ValidationException $e) {
            $this->addError('edit_roles', collect($e->errors())->flatten()->first());

            return;
        }

        if ($collaborator && ! $collaborator->user_id) {
            $collaborator->update(['user_id' => $target->id]);
        }

        $target->syncRoles($this->edit_roles);
        $target->update([
            'uses_custom_permissions' => $this->edit_uses_custom,
            'updated_by' => Auth::id(),
        ]);

        if ($this->edit_uses_custom) {
            $perms = $this->edit_direct_permissions;
            if (! Auth::user()->hasRole('super_admin')) {
                $perms = array_values(array_diff($perms, ['system.code.manage']));
            }
            $target->syncPermissions($perms);
        } else {
            $target->syncPermissions([]);
        }

        $sync = [];
        foreach ($this->edit_categories as $cid) {
            $sync[(int) $cid] = ['is_active' => true, 'assigned_by' => Auth::id()];
        }
        $target->academicCategories()->sync($sync);

        AuditService::log('governance.user_access_updated', 'direccion', $target, null, [
            'roles' => $this->edit_roles,
            'uses_custom_permissions' => $this->edit_uses_custom,
            'categories' => $this->edit_categories,
        ]);

        $this->editingUserId = null;
        $this->feedback = 'Accesos, roles y permisos por persona actualizados correctamente.';
    }

    public function applyBasePermissions(int $userId, GovernanceService $governance): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.users.manage'), 403);
        $target = User::findOrFail($userId);
        $governance->applyBaseRolePermissions(Auth::user(), $target);
        $this->feedback = sprintf('Permisos base del cargo aplicados a %s.', $target->name);
    }

    public function issueTempPassword(int $userId, GovernanceService $governance): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.users.manage'), 403);
        $target = User::findOrFail($userId);
        try {
            $temp = $governance->issueTemporaryPassword(Auth::user(), $target);
        } catch (ValidationException $e) {
            $this->addError('password', collect($e->errors())->flatten()->first());

            return;
        }
        $this->issuedTempPassword = sprintf('Clave temporal para %s (%s): %s (deberá cambiarla al ingresar)', $target->name, $target->email, $temp);
    }

    public function toggleUserActive(int $userId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.users.manage'), 403);
        $target = User::findOrFail($userId);
        if ($target->id === Auth::id()) {
            $this->addError('users', 'No puede desactivar su propia cuenta.');

            return;
        }
        if ($target->hasRole('super_admin') && ! Auth::user()->hasRole('super_admin')) {
            $this->addError('users', 'Solo un Super Administrador puede modificar el estado de otro Super Administrador.');

            return;
        }

        $next = ! $target->is_active;
        $target->update([
            'is_active' => $next,
            'deactivated_at' => $next ? null : now(),
            'deactivated_by' => $next ? null : Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        AuditService::log($next ? 'governance.user_activated' : 'governance.user_deactivated', 'direccion', $target);
        $this->feedback = $next ? 'Usuario reactivado.' : 'Usuario inactivado (sin borrado físico).';
    }

    public function cloneUserPermissions(GovernanceService $governance): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.users.manage'), 403);

        $this->validate([
            'clone_source_user_id' => ['required', 'integer', 'exists:users,id'],
            'clone_name' => ['required', 'string', 'max:140'],
            'clone_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'clone_id_num' => ['required', 'string', 'max:40', 'unique:users,identification_number'],
            'clone_phone' => ['required', 'string', 'max:30'],
            'clone_temp_password' => ['required', 'string', 'min:8'],
        ]);

        $source = User::findOrFail($this->clone_source_user_id);

        try {
            $cloned = $governance->cloneUserPermissionsToNewUser(
                actor: Auth::user(),
                sourceUser: $source,
                newUserData: [
                    'name' => trim($this->clone_name),
                    'email' => strtolower(trim($this->clone_email)),
                    'identification_number' => trim($this->clone_id_num),
                    'phone' => trim($this->clone_phone),
                ],
                temporaryPassword: $this->clone_temp_password
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reset(['clone_staff_id', 'clone_name', 'clone_email', 'clone_id_num', 'clone_phone', 'clone_temp_password']);
        $this->feedback = sprintf('Permisos clonados de forma segura al nuevo usuario %s (exige cambio de contraseña al entrar).', $cloned->email);
    }

    public function addPicklistItem(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.picklists.manage'), 403);

        $this->validate([
            'pick_group' => ['required', 'string', 'max:80'],
            'pick_code' => ['required', 'string', 'max:80'],
            'pick_label' => ['required', 'string', 'max:160'],
        ]);

        $item = Picklist::firstOrCreate(
            ['group_key' => $this->pick_group, 'code' => strtolower(trim($this->pick_code))],
            ['label' => trim($this->pick_label), 'sort_order' => 100, 'is_active' => true, 'is_system' => false]
        );

        AuditService::log('picklist.added', 'direccion', $item);
        $this->reset(['pick_code', 'pick_label']);
        $this->feedback = 'Valor de catálogo agregado correctamente.';
    }

    public function togglePicklistActive(int $id): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.picklists.manage'), 403);
        $item = Picklist::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        AuditService::log('picklist.toggled', 'direccion', $item, null, ['is_active' => $item->is_active]);
    }

    public function saveSettings(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('direccion.settings.manage'), 403);

        $this->validate([
            'set_institution_name' => ['required', 'string', 'max:200'],
            'set_short_name' => ['required', 'string', 'max:80'],
            'set_provisional_days' => ['required', 'integer', 'min:1', 'max:60'],
            'set_online_notice' => ['required', 'string', 'min:10'],
            'set_attention_hours' => ['required', 'string', 'min:5'],
            'set_gender_policy_message' => ['required', 'string', 'min:10'],
            'set_receipt_prefix' => ['required', 'string', 'max:15'],
        ]);

        $setting = InstitutionSetting::query()->firstOrFail();
        $old = $setting->only(['online_enrollment_provisional_days', 'online_enrollment_notice', 'attention_hours', 'gender_policy_message', 'enabled_modules']);

        $modulesMap = [];
        foreach (array_keys(config('cecap.modules')) as $modKey) {
            $modulesMap[$modKey] = (bool) ($this->set_enabled_modules[$modKey] ?? false);
        }
        $modulesMap['direccion'] = true; // Nunca bloquear el panel de dirección

        $setting->update([
            'institution_name' => trim($this->set_institution_name),
            'short_name' => trim($this->set_short_name),
            'phone' => trim($this->set_phone),
            'email' => trim($this->set_email),
            'address' => trim($this->set_address),
            'online_enrollment_provisional_days' => $this->set_provisional_days,
            'online_enrollment_notice' => trim($this->set_online_notice),
            'attention_hours' => trim($this->set_attention_hours),
            'gender_policy_message' => trim($this->set_gender_policy_message),
            'require_financial_solvency_for_graduation' => $this->set_require_solvency,
            'enabled_modules' => $modulesMap,
            'director_name' => trim($this->set_director_name),
            'director_title' => trim($this->set_director_title),
            'receipt_prefix' => strtoupper(trim($this->set_receipt_prefix)),
            'updated_by' => Auth::id(),
        ]);

        InstitutionSetting::clearCache();

        AuditService::log('settings.updated', 'direccion', $setting, $old, [
            'online_enrollment_provisional_days' => $this->set_provisional_days,
            'enabled_modules' => $modulesMap,
        ]);

        $this->feedback = 'Configuración institucional (Tab 5) y módulos globales actualizados.';
    }

    public function render()
    {
        $auditQuery = AuditLog::query()->latest('id');
        if ($this->audit_module_filter !== '') {
            $auditQuery->where('module', $this->audit_module_filter);
        }

        return view('livewire.direction-page', [
            'kpis' => [
                'students' => Student::count(),
                'enrollments_confirmed' => Enrollment::whereIn('status', ['confirmada', 'en_curso', 'finalizada', 'graduada'])->count(),
                'enrollments_pending' => Enrollment::whereIn('status', ['preinscrita', 'documentacion_pendiente'])->count(),
                'total_collected' => (float) PaymentReceipt::where('status', 'emitido')->sum('total_amount'),
                'pending_receivables' => (float) FeeCharge::whereIn('status', ['pendiente', 'parcial'])->get()->sum(fn ($c) => $c->balance),
                'graduations' => GraduationRecord::where('status', 'emitido')->count(),
            ],
            'users' => User::with(['roles', 'academicCategories'])->orderBy('id')->get(),
            'collaborators' => StaffMember::where('status', 'activo')->orderBy('first_name')->get(),
            'rolesCatalog' => config('cecap.roles'),
            'allPermissions' => Permission::orderBy('name')->pluck('name')->all(),
            'categories' => AcademicCategory::orderBy('name')->get(),
            'auditLogs' => $auditQuery->limit(80)->get(),
            'picklists' => Picklist::orderBy('group_key')->orderBy('sort_order')->get(),
            'provinces' => CrProvince::withCount('cantons')->orderBy('code')->get(),
            'modulesCatalog' => config('cecap.modules'),
            'isSuperAdmin' => Auth::user()->hasRole('super_admin'),
            'canCodeManage' => Auth::user()->hasEffectivePermission('system.code.manage'),
        ]);
    }
}
