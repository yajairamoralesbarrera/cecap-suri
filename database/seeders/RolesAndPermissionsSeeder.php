<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        // Sistema (exclusivo Super Admin)
        'system.code.manage',

        // Panel y Portal Alumna
        'panel.view',
        'portal.alumna.view',

        // Académico
        'academico.view',
        'academico.manage',
        'academico.schedules.manage',

        // Admisiones e Inscripción
        'admisiones.view',
        'admisiones.manage',
        'admisiones.exceptions.authorize',

        // Seguimiento Académico
        'seguimiento.view',
        'seguimiento.attendance.manage',
        'seguimiento.grades.manage',
        'seguimiento.grades.rectify',
        'seguimiento.soft_skills.manage',

        // Finanzas y Caja
        'finanzas.view',
        'finanzas.charges.manage',
        'finanzas.scholarships.manage',
        'finanzas.payments.collect',
        'finanzas.payments.reverse',

        // Graduación y Diplomas
        'graduacion.view',
        'graduacion.manage',
        'graduacion.solvency_exception.authorize',

        // Personal y Contratos CR
        'personal.view',
        'personal.manage',
        'personal.payroll.manage',

        // Talleres y Actividades
        'actividades.view',
        'actividades.manage',

        // Repositorio Documental
        'documentos.view',
        'documentos.manage',

        // Ventanilla Única 360°
        'consultas.view',

        // Dirección y Gobernanza
        'direccion.view',
        'direccion.users.manage',
        'direccion.settings.manage',
        'direccion.picklists.manage',
        'direccion.audit.view',
        'direccion.exports.csv',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // 1. Super Administrador (ÚNICO con system.code.manage)
        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions(self::PERMISSIONS);

        // 2. Dirección / Administrador operativo (todo excepto system.code.manage)
        $direccion = Role::findOrCreate('direccion', 'web');
        $direccion->syncPermissions(array_values(array_diff(self::PERMISSIONS, ['system.code.manage'])));

        // 3. Secretaría
        $secretaria = Role::findOrCreate('secretaria', 'web');
        $secretaria->syncPermissions([
            'panel.view',
            'academico.view',
            'admisiones.view',
            'admisiones.manage',
            'admisiones.exceptions.authorize',
            'seguimiento.view',
            'finanzas.view',
            'graduacion.view',
            'actividades.view',
            'actividades.manage',
            'documentos.view',
            'documentos.manage',
            'consultas.view',
        ]);

        // 4. Administración / Caja
        $caja = Role::findOrCreate('administracion_caja', 'web');
        $caja->syncPermissions([
            'panel.view',
            'admisiones.view',
            'finanzas.view',
            'finanzas.charges.manage',
            'finanzas.scholarships.manage',
            'finanzas.payments.collect',
            'finanzas.payments.reverse',
            'personal.view',
            'personal.payroll.manage',
            'documentos.view',
            'consultas.view',
        ]);

        // 5. Coordinación Académica
        $coordAcad = Role::findOrCreate('coordinacion_academica', 'web');
        $coordAcad->syncPermissions([
            'panel.view',
            'academico.view',
            'academico.manage',
            'academico.schedules.manage',
            'admisiones.view',
            'seguimiento.view',
            'seguimiento.attendance.manage',
            'seguimiento.grades.manage',
            'seguimiento.grades.rectify',
            'seguimiento.soft_skills.manage',
            'graduacion.view',
            'graduacion.manage',
            'personal.view',
            'actividades.view',
            'actividades.manage',
            'documentos.view',
            'consultas.view',
        ]);

        // 6. Coordinador de Área / Categoría (restringido por user_academic_categories)
        $coordCat = Role::findOrCreate('coordinador_categoria', 'web');
        $coordCat->syncPermissions([
            'panel.view',
            'academico.view',
            'academico.manage',
            'academico.schedules.manage',
            'seguimiento.view',
            'seguimiento.attendance.manage',
            'seguimiento.grades.manage',
            'seguimiento.grades.rectify',
            'seguimiento.soft_skills.manage',
            'graduacion.view',
            'consultas.view',
        ]);

        // 7. Profesora
        $profesora = Role::findOrCreate('profesora', 'web');
        $profesora->syncPermissions([
            'panel.view',
            'academico.view',
            'seguimiento.view',
            'seguimiento.attendance.manage',
            'seguimiento.grades.manage',
            'seguimiento.grades.rectify',
            'seguimiento.soft_skills.manage',
            'actividades.view',
            'documentos.view',
        ]);

        // 8. Alumna (auto-aprovisionada en registro con permisos estándar del portal)
        $alumna = Role::findOrCreate('alumna', 'web');
        $alumna->syncPermissions([
            'panel.view',
            'portal.alumna.view',
            'documentos.view',
        ]);
    }
}
