<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, PreventsPhysicalDeletion;

    protected $fillable = [
        'name',
        'email',
        'identification_type',
        'identification_number',
        'phone',
        'gender',
        'password',
        'must_change_password',
        'password_changed_at',
        'uses_custom_permissions',
        'is_active',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'uses_custom_permissions' => 'boolean',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    public function academicCategories(): BelongsToMany
    {
        return $this->belongsToMany(AcademicCategory::class, 'user_academic_categories')
            ->withPivot(['is_active', 'assigned_by'])
            ->withTimestamps();
    }

    public function activeAcademicCategoryIds(): array
    {
        return $this->academicCategories()
            ->wherePivot('is_active', true)
            ->pluck('academic_categories.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isCategoryScopedCoordinator(): bool
    {
        return $this->hasRole('coordinador_categoria')
            && ! $this->hasAnyRole(['super_admin', 'direccion', 'coordinacion_academica']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'direccion']);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function staffMember(): HasOne
    {
        return $this->hasOne(StaffMember::class);
    }

    /**
     * Effective permission check respecting per-user custom permissions mode.
     * When uses_custom_permissions is true, ONLY direct permissions apply.
     */
    public function hasEffectivePermission(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        if ($this->uses_custom_permissions) {
            return $this->hasDirectPermission($permission);
        }

        return $this->can($permission);
    }

    public function getRoleLabelsAttribute(): string
    {
        $map = config('cecap.role_labels', []);

        return $this->getRoleNames()
            ->map(fn (string $role) => $map[$role] ?? $role)
            ->implode(', ');
    }
}
