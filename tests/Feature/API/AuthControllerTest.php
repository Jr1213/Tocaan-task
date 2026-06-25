<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
            'password_confirmation' => 'Passw0rd!xx',
        ], $overrides);
    }

    // ############################### start register tests ################################
    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson(route('auth.register'), $this->validPayload());

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'User registered successfully.',
                'data' => [
                    'user' => [
                        'name' => 'Jane Doe',
                        'email' => 'jane@example.com',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
        $this->assertIsString($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_register_does_not_expose_password(): void
    {
        $response = $this->postJson(route('auth.register'), $this->validPayload());

        $response->assertJsonMissingPath('data.user.password');
    }

    public function test_register_hashes_the_password_once(): void
    {
        $this->postJson(route('auth.register'), $this->validPayload())->assertOk();

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertNotSame('Passw0rd!xx', $user->password);
        $this->assertTrue(Hash::check('Passw0rd!xx', $user->password));
    }

    public function test_register_issued_token_authenticates_protected_routes(): void
    {
        $token = $this->postJson(route('auth.register'), $this->validPayload())
            ->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJson(['data' => ['user' => ['email' => 'jane@example.com']]]);
    }

    public function test_register_requires_name(): void
    {
        $this->postJson(route('auth.register'), $this->validPayload(['name' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_register_requires_valid_email(): void
    {
        $this->postJson(route('auth.register'), $this->validPayload(['email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson(route('auth.register'), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_register_requires_strong_password(): void
    {
        $this->postJson(route('auth.register'), $this->validPayload([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_register_requires_matching_password_confirmation(): void
    {
        $this->postJson(route('auth.register'), $this->validPayload([
            'password_confirmation' => 'Different!1xx',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    // ############################### end register tests ################################

    // ############################### start login tests ################################
    public function test_login_returns_token_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'User logged in successfully.',
                'data' => [
                    'user' => ['email' => 'jane@example.com'],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
        $this->assertIsString($response->json('data.token'));
    }

    public function test_login_token_authenticates_protected_routes(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
        ]);

        $token = $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
        ])->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJson(['data' => ['user' => ['email' => 'jane@example.com']]]);
    }

    public function test_login_does_not_expose_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
        ])->assertJsonMissingPath('data.user.password');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Passw0rd!xx',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'WrongPass!1xx',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Password or email is incorrect']);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson(route('auth.login'), [
            'email' => 'ghost@example.com',
            'password' => 'Passw0rd!xx',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Password or email is incorrect']);
    }

    public function test_login_requires_email(): void
    {
        $this->postJson(route('auth.login'), ['password' => 'Passw0rd!xx'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_requires_password(): void
    {
        $this->postJson(route('auth.login'), ['email' => 'jane@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_login_requires_valid_email_format(): void
    {
        $this->postJson(route('auth.login'), [
            'email' => 'not-an-email',
            'password' => 'Passw0rd!xx',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    // ############################### end login tests ################################

    // ############################### start me tests ################################
    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Authenticated user retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Jane Doe',
                        'email' => 'jane@example.com',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user' => ['id', 'name', 'email', 'created_at', 'updated_at']],
            ]);
    }

    public function test_me_does_not_expose_password(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson(route('auth.me'))
            ->assertUnauthorized();
    }

    public function test_me_rejects_invalid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-valid-token')
            ->getJson(route('auth.me'))
            ->assertUnauthorized();
    }

    // ############################### end me tests ################################

    // ############################### start logout tests ################################
    public function test_logout_succeeds_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson(route('auth.logout'))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'User logged out successfully.',
            ]);
    }

    public function test_logout_invalidates_the_presented_token(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson(route('auth.logout'))
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('auth.me'))
            ->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->deleteJson(route('auth.logout'))
            ->assertUnauthorized();
    }

    // ############################### end logout tests ################################

    // ############################### start refresh tests ################################
    public function test_refresh_returns_a_new_token(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('auth.refresh'))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully.',
            ])
            ->assertJsonStructure(['success', 'message', 'data' => ['token']]);

        $newToken = $response->json('data.token');
        $this->assertIsString($newToken);
        $this->assertNotSame($token, $newToken);
    }

    public function test_refresh_new_token_authenticates_protected_routes(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $token = JWTAuth::fromUser($user);

        $newToken = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('auth.refresh'))
            ->json('data.token');

        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJson(['data' => ['user' => ['email' => 'jane@example.com']]]);
    }

    public function test_refresh_invalidates_the_old_token(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('auth.refresh'))
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('auth.me'))
            ->assertUnauthorized();
    }

    public function test_refresh_requires_authentication(): void
    {
        $this->postJson(route('auth.refresh'))
            ->assertUnauthorized();
    }

    // ############################### end refresh tests ################################

}
