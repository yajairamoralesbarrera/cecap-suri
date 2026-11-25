<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Arranque limpio CeCap v3.0:
     * Solo siembra roles/permisos, catálogos oficiales CR, configuración institucional
     * y el único usuario inicial Super Administrador (0 datos ficticios).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CostaRicaCatalogSeeder::class,
        ]);

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@cecap.or.cr'],
            [
                'name' => 'Super Administrador CeCap',
                'identification_type' => 'cedula_nacional',
                'identification_number' => '1-0000-0001',
                'phone' => '+506 2253-0000',
                'gender' => 'femenino',
                'password' => Hash::make('Y4y4&K1k3'),
                'must_change_password' => false,
                'password_changed_at' => now(),
                'uses_custom_permissions' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }
    }
}
