<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Don't re-create or overwrite an admin account if one already
        // exists. This keeps re-seeding an existing installation safe —
        // the current admin's credentials are left untouched instead of
        // being reset to defaults.
        if (User::where('is_admin', true)->exists()) {
            return;
        }

        // Admin credentials are defined during installation and stored in
        // the .env file. Sensible defaults are kept so seeding still works
        // out of the box (e.g. for local development / tests).
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
    }
}
