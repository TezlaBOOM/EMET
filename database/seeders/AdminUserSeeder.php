<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var User $admin */
        $admin = User::updateOrCreate(
            ['email' => 'admin@admin.lan'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin'),
                'theme' => 'system',
                'setup_completed' => false,
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
