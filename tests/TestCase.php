<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never run tests (RefreshDatabase wipes tables) against the real database.
        if (! str_ends_with((string) config('database.connections.'.config('database.default').'.database'), '_test')) {
            throw new RuntimeException('Tests need a *_test database (see phpunit.xml).');
        }
    }

    /**
     * @param  list<string>  $permissions  empty with $admin = true gives the administrator role
     */
    protected function user(array $permissions = [], bool $admin = false): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.uniqid(),
            'is_admin' => $admin,
            'permissions' => $admin ? [] : $permissions,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->fresh();
    }

    protected function admin(): User
    {
        return $this->user(admin: true);
    }

    protected function allPermissions(): array
    {
        return Permissions::all();
    }
}
