<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Crea el operador inicial a partir de ADMIN_EMAIL / ADMIN_PASSWORD si aún no
 * existe. No sobrescribe la contraseña de una cuenta existente, así que es
 * seguro ejecutarlo en cada arranque del contenedor.
 */
Artisan::command('prowl:ensure-admin', function () {
    $email = config('prowltracker.admin.email');
    $password = config('prowltracker.admin.password');

    if (blank($email) || blank($password)) {
        $this->warn('ADMIN_EMAIL / ADMIN_PASSWORD no definidos; se omite la creación del operador.');

        return 0;
    }

    if (strlen($password) < 12) {
        $this->error('ADMIN_PASSWORD debe tener al menos 12 caracteres.');

        return 1;
    }

    if (User::where('email', $email)->exists()) {
        $this->info("El operador {$email} ya existe; no se modifica.");

        return 0;
    }

    $user = User::forceCreate([
        'name' => config('prowltracker.admin.name', 'Operador'),
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
    ]);

    $this->info("Operador {$user->email} creado.");

    return 0;
})->purpose('Crea el operador inicial definido en ADMIN_EMAIL / ADMIN_PASSWORD');
