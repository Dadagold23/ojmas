<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+2348000000000',
            'institution' => 'Federal Polytechnic Offa',
            'country' => 'Nigeria',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_persists_required_profile_fields(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'profile@example.com',
            'phone' => ' +2348000000000 ',
            'institution' => ' University of Lagos ',
            'country' => ' Nigeria ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'profile@example.com',
            'phone' => '+2348000000000',
            'institution' => 'University of Lagos',
            'country' => 'Nigeria',
        ]);

        $user = User::where('email', 'profile@example.com')->firstOrFail();

        $this->assertSame('+2348000000000', $user->phone);
        $this->assertSame('University of Lagos', $user->institution);
        $this->assertSame('Nigeria', $user->country);
    }

    public function test_registration_requires_profile_fields(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'missing-fields@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['phone', 'institution', 'country']);
        $this->assertGuest();
    }
}
