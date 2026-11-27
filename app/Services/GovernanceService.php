<?php

namespace App\Services;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class GovernanceService
{
    public function requiresCollaboratorRecord(array $targetRoles): bool
    {
        foreach ($targetRoles as $role) {
            if ($role !== 'super_admin') {
                return true;
            }
        }

        return false;
    }

    public function assertCollaboratorRegistered(
        array $targetRoles,
        ?string $identificationNumber,
        ?string $email = null,
        ?int $userId = null,
        string $errorKey = 'new_user_id_num'
    ): ?StaffMember {
        if (! $this->requiresCollaboratorRecord($targetRoles)) {
            return null;
        }

        $staff = null;
        if ($userId) {
            $staff = StaffMember::where('user_id', $userId)->first();
        }
        if (! $staff && filled($identificationNumber)) {
            $staff = StaffMember::findByIdentification((string) $identificationNumber);
        }
        if (! $staff && filled($email)) {
            $staff = StaffMember::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email))])->first();
        }

        if (! $staff || $staff->status !== 'activo') {
            throw ValidationException::withMessages([
                $errorKey => 'El único rol exento es Super Administrador. Para asignar cualquier otro rol, la persona debe estar registrada y activa primero en el módulo Colaboradores.',
            ]);
        }

        return $staff;
    }

    public function assertCanAssignRoles(User $actor, array $targetRoles, ?User $targetUser = null): void
    {
        $currentRoles = $targetUser ? $targetUser->getRoleNames()->all() : [];
        $addingSuperAdmin = in_array('super_admin', $targetRoles, true) && ! in_array('super_admin', $currentRoles, true);
        $removingSuperAdmin = ! in_array('super_admin', $targetRoles, true) && in_array('super_admin', $currentRoles, true);

        if (($addingSuperAdmin || $removingSuperAdmin) && ! $actor->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'roles' => 'Solo un Super Administrador puede conceder o revocar el rol Super Administrador.',
            ]);
        }

        $addingAdmin = in_array('direccion', $targetRoles, true) && ! in_array('direccion', $currentRoles, true);
        $removingAdmin = ! in_array('direccion', $targetRoles, true) && in_array('direccion', $currentRoles, true);

        if (($addingAdmin || $removingAdmin) && ! $actor->hasAnyRole(['super_admin', 'direccion'])) {
            throw ValidationException::withMessages([
                'roles' => 'Solo Dirección o Super Administrador puede conceder o revocar el rol Administrador (Dirección).',
            ]);
        }
    }

    public function applyBaseRolePermissions(User $actor, User $target): void
    {
        $roleNames = $target->getRoleNames()->all();
        $permissions = Role::query()
            ->whereIn('name', $roleNames)
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $r) => $r->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();

        $target->update(['uses_custom_permissions' => false, 'updated_by' => $actor->id]);
        $target->syncPermissions($permissions);

        AuditService::log(
            event: 'governance.base_permissions_applied',
            module: 'direccion',
            auditable: $target,
            newValues: ['roles' => $roleNames, 'permissions_count' => count($permissions)]
        );
    }

    public function cloneUserPermissionsToNewUser(
        User $actor,
        User $sourceUser,
        array $newUserData,
        string $temporaryPassword
    ): User {
        $roles = $sourceUser->getRoleNames()->all();
        $this->assertCanAssignRoles($actor, $roles);

        if (strtolower(trim($newUserData['email'])) === strtolower(trim($sourceUser->email))) {
            throw ValidationException::withMessages([
                'email' => 'Para clonar permisos debe ingresar un correo electrónico nuevo y distinto al usuario origen.',
            ]);
        }

        if (! empty($newUserData['identification_number']) && $newUserData['identification_number'] === $sourceUser->identification_number) {
            throw ValidationException::withMessages([
                'identification_number' => 'Para clonar permisos debe ingresar una identificación personal nueva y distinta.',
            ]);
        }

        $collaborator = $this->assertCollaboratorRegistered(
            targetRoles: $roles,
            identificationNumber: $newUserData['identification_number'] ?? null,
            email: $newUserData['email'] ?? null,
            errorKey: 'clone_id_num'
        );

        $newUser = User::create([
            'name' => $newUserData['name'],
            'email' => strtolower(trim($newUserData['email'])),
            'identification_type' => $collaborator?->identification_type ?? ($newUserData['identification_type'] ?? 'cedula_nacional'),
            'identification_number' => $newUserData['identification_number'] ?? null,
            'phone' => $newUserData['phone'] ?? null,
            'gender' => $newUserData['gender'] ?? 'femenino',
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'uses_custom_permissions' => (bool) $sourceUser->uses_custom_permissions,
            'is_active' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        if ($collaborator && ! $collaborator->user_id) {
            $collaborator->update(['user_id' => $newUser->id]);
        }

        $newUser->syncRoles($roles);

        if ($sourceUser->uses_custom_permissions) {
            $newUser->syncPermissions($sourceUser->getDirectPermissions()->pluck('name')->all());
        }

        $categoryIds = $sourceUser->activeAcademicCategoryIds();
        if (! empty($categoryIds)) {
            $syncData = [];
            foreach ($categoryIds as $cid) {
                $syncData[$cid] = ['is_active' => true, 'assigned_by' => $actor->id];
            }
            $newUser->academicCategories()->sync($syncData);
        }

        AuditService::log(
            event: 'governance.permissions_cloned',
            module: 'direccion',
            auditable: $newUser,
            newValues: [
                'source_user_id' => $sourceUser->id,
                'source_email' => $sourceUser->email,
                'roles' => $roles,
                'uses_custom_permissions' => $sourceUser->uses_custom_permissions,
                'staff_member_id' => $collaborator?->id,
            ]
        );

        return $newUser;
    }

    public function issueTemporaryPassword(User $actor, User $target, ?string $customPassword = null): string
    {
        if ($target->hasRole('super_admin') && ! $actor->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'password' => 'Solo un Super Administrador puede emitir clave temporal a otro Super Administrador.',
            ]);
        }

        $tempPassword = $customPassword ?: ('CeCap'.Str::random(5).'!'.random_int(10, 99));

        $target->update([
            'password' => Hash::make($tempPassword),
            'must_change_password' => true,
            'updated_by' => $actor->id,
        ]);

        AuditService::log(
            event: 'security.temporary_password_issued',
            module: 'direccion',
            auditable: $target,
            newValues: ['must_change_password' => true]
        );

        return $tempPassword;
    }
}
