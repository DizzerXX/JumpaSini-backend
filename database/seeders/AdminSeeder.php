<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@jumpa.sini')],
            [
                'name'     => 'JumpaSini Admin',
                'password' => bcrypt(env('ADMIN_PASSWORD', 'changeme123')),
            ]
        );
    }
}
