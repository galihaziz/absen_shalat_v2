<?php

namespace Tests\Feature;

use AbsenShalat\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_requires_a_unique_recovery_email_while_regular_accounts_do_not_use_one(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $this->post('/data_user', [
            'username' => 'admin-kedua',
            'nama' => 'Admin Kedua',
            'role' => 'admin',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->post('/data_user', [
            'username' => 'admin-kedua',
            'nama' => 'Admin Kedua',
            'role' => 'admin',
            'email' => 'admin-kedua@example.com',
            'password' => 'password123',
        ])->assertRedirect('/data_user');
        $this->assertDatabaseHas('users', [
            'username' => 'admin-kedua',
            'email' => 'admin-kedua@example.com',
            'role' => 'admin',
        ]);

        $this->post('/data_user', [
            'username' => 'operator',
            'nama' => 'Operator',
            'role' => 'absensi',
            'email' => '',
            'password' => 'password123',
        ])->assertRedirect('/data_user');
        $this->assertDatabaseHas('users', ['username' => 'operator', 'email' => null]);
    }

    public function test_admin_can_reset_password_using_email_notification(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();

        $this->get('/staff/login')->assertOk()->assertSee('Lupa password admin?');
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => $admin->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password-123', $admin->fresh()->password));
    }

    public function test_school_logo_is_shown_on_admin_and_student_portal_pages(): void
    {
        $logoUrl = asset('images/irma-logo.png').'?v='.md5_file(public_path('images/irma-logo.png'));

        $this->get('/staff/login')->assertOk()->assertSee($logoUrl);
        $this->get('/siswa/login')->assertOk()->assertSee($logoUrl);
    }

    public function test_non_admin_email_does_not_receive_password_reset_notification(): void
    {
        Notification::fake();
        $this->makeAdmin();

        $this->post('/forgot-password', ['email' => 'operator@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_password_reset_notification_includes_the_school_logo_and_reset_link(): void
    {
        $admin = $this->makeAdmin();
        $message = (new ResetPassword('reset-token'))->toMail($admin);

        $this->assertSame('Tautan Reset Password Admin', $message->subject);
        $this->assertSame('emails.password-reset', $message->view['html']);
        $this->assertSame('emails.password-reset-text', $message->view['text']);

        $html = view($message->view['html'], $message->viewData)->render();
        $text = view($message->view['text'], $message->viewData)->render();

        $this->assertStringContainsString('src="cid:school-logo"', $html);
        $this->assertFileExists(public_path('images/al-hidayah-logo.png'));
        $resetUrl = route('password.reset', [
            'token' => 'reset-token',
            'email' => $admin->email,
        ]);
        $this->assertStringContainsString($resetUrl, $html);
        $this->assertStringContainsString($resetUrl, $text);
    }

    public function test_password_reset_links_use_configured_app_url_not_forwarded_host(): void
    {
        config(['app.url' => 'https://school.example.test']);
        $admin = $this->makeAdmin();

        $this->withServerVariables([
            'HTTP_HOST' => 'school.example.test',
            'HTTP_X_FORWARDED_HOST' => 'attacker.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $message = (new ResetPassword('reset-token'))->toMail($admin);
        $html = view($message->view['html'], $message->viewData)->render();

        $this->assertStringContainsString('https://school.example.test/password/reset/reset-token?', $html);
        $this->assertStringNotContainsString('attacker.example', $html);
    }

    private function makeAdmin(): User
    {
        return User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'nama' => 'Administrator',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);
    }
}
