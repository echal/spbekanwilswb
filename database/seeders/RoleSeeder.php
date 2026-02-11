<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Admin', 'Operator', 'Auditor', 'Pimpinan'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@spbe.local'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password'),
            ]
        );
        $admin->assignRole('Admin');

        // Operator user (set unit_kerja_id setelah ada data unit kerja)
        $operator = User::firstOrCreate(
            ['email' => 'operator@spbe.local'],
            [
                'name' => 'Operator',
                'password' => bcrypt('password'),
                'unit_kerja_id' => null,
            ]
        );
        $operator->assignRole('Operator');

        // Auditor user
        $auditor = User::firstOrCreate(
            ['email' => 'auditor@spbe.local'],
            [
                'name' => 'Auditor',
                'password' => bcrypt('password'),
            ]
        );
        $auditor->assignRole('Auditor');
    }
}
