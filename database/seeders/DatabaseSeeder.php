<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = trim((string) env('SEED_ADMIN_EMAIL', ''));
        $adminPassword = (string) env('SEED_ADMIN_PASSWORD', '');

        if ($adminEmail !== '' && $adminPassword !== '') {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Administrator',
                    'username' => 'admin',
                    'contact_number' => '09123456789',
                    'password' => $adminPassword,
                    'role' => User::ROLE_ADMIN,
                ]
            );
        }

        $this->call([
            SiteSettingsSeeder::class,
            TherapistSeeder::class,
            SpaServiceSeeder::class,
            SpaTimeSlotSeeder::class,
        ]);
    }
}
