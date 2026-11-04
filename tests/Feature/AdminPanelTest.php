<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_any_user_can_open_the_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
    }

    public function test_only_master_can_open_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();

        $master = User::factory()->create(['is_master' => true]);
        $this->actingAs($master);

        foreach (['/admin/users', '/admin/users/create', "/admin/users/{$master->id}", "/admin/users/{$master->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_seeder_creates_the_master_from_the_environment(): void
    {
        config(['app.admin' => ['name' => 'Chefe', 'mail' => 'chefe@exemplo.com', 'password' => 'segredo-123']]);

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', 'chefe@exemplo.com')->count());
        $this->assertTrue(User::where('email', 'chefe@exemplo.com')->firstOrFail()->is_master);
    }
}
