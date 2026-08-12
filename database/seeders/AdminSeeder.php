<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = config('jumpa.admin.email');
        $password = config('jumpa.admin.password');

        // The deploy runs this seeder on every boot. Without an explicit password
        // we would otherwise create a publicly reachable admin with a well-known
        // default — refuse instead, and say so loudly in the deploy log.
        if (blank($password)) {
            $this->command?->warn(
                'AdminSeeder skipped: ADMIN_PASSWORD is not set. '
                . 'Set it and redeploy to create the admin account.'
            );

            return;
        }

        Admin::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'JumpaSini Admin',
                'password' => bcrypt($password),
            ]
        );
    }
}
