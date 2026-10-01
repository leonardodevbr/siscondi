<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Manifestation;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = Municipality::firstOrCreate(
            ['slug' => 'cafarnaum-ba'],
            ['name' => 'Cafarnaum', 'state' => 'BA']
        );

        foreach (['Centro', 'Recife', 'Junco', 'Aroeira'] as $name) {
            Community::firstOrCreate(['municipality_id' => $municipality->id, 'name' => $name]);
        }

        foreach ([
            ['Sanfoneiro(a)', 'Música'],
            ['Violeiro(a)', 'Música'],
            ['Artesanato em palha', 'Artesanato'],
            ['Artesanato em barro', 'Artesanato'],
            ['Quadrilha junina', 'Cultura popular'],
            ['Reisado', 'Cultura popular'],
            ['História oral', 'Memória'],
            ['Culinária tradicional', 'Cultura alimentar'],
        ] as [$name, $category]) {
            Manifestation::firstOrCreate(['name' => $name], ['category' => $category]);
        }

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@cultura.local'],
            [
                'municipality_id' => $municipality->id,
                'name' => 'Administrador Cultura',
                'username' => 'admin',
                'password' => Hash::make('password'),
            ]
        );

        if (! $admin->hasRole($role)) {
            $admin->assignRole($role);
        }
    }
}
