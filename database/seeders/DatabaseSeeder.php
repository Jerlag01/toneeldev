<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\MailTemplates;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Base data: default roles, mail templates and the story. Safe to run again; nothing existing is overwritten.
     * Create the first administrator with: php artisan toneel:admin you@example.com
     */
    public function run(): void
    {
        foreach (Permissions::defaultRoles() as $name => $role) {
            Role::query()->firstOrCreate(['name' => $name], $role);
        }

        MailTemplates::sync();

        $this->call(StorySeeder::class);
        $this->call(ProductionsAndShopSeeder::class);
    }
}
