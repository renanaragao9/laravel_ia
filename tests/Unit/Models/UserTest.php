<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Filament\Panel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function user(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret123',
        ], $attributes));
    }

    public function test_can_create_user(): void
    {
        $user = $this->user();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'ana@example.com']);
    }

    public function test_can_read_user(): void
    {
        $user = $this->user();

        $this->assertSame('Ana', User::find($user->id)->name);
    }

    public function test_can_update_user(): void
    {
        $user = $this->user();

        $user->update(['name' => 'Ana Maria']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Ana Maria']);
    }

    public function test_password_is_hashed(): void
    {
        $user = $this->user();

        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_email_is_unique(): void
    {
        $this->user();

        $this->expectException(QueryException::class);

        $this->user();
    }

    public function test_is_master_defaults_to_false(): void
    {
        $this->assertFalse($this->user()->fresh()->is_master);
    }

    public function test_any_user_can_access_panel(): void
    {
        $this->assertTrue($this->user()->canAccessPanel(Panel::make()));
    }
}
