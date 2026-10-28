<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Sembrar únicamente el usuario administrador inicial para permitir el acceso al sistema desde cero.
     */
    public function run(): void
    {
        User::create([
            'name'     => 'Administrador',
            'email'    => 'admin@atelier.com',
            'password' => Hash::make('admin123'),
            'rol'      => 'admin',
        ]);
    }
}
