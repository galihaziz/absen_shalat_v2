<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $email = $notifiable->getEmailForPasswordReset();
            $expireMinutes = (int) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60,
            );
            $data = [
                'appName' => config('app.name', 'Rekap Absensi Solat'),
                'homeUrl' => rtrim((string) config('app.url'), '/').route('login', [], false),
                'logoUrl' => 'cid:school-logo',
                'resetUrl' => rtrim((string) config('app.url'), '/').route('password.reset', [
                    'token' => $token,
                    'email' => $email,
                ], false),
                'expireMinutes' => $expireMinutes,
            ];

            return (new MailMessage)
                ->subject('Tautan Reset Password Admin')
                ->view('emails.password-reset', $data)
                ->text('emails.password-reset-text', $data)
                ->withSymfonyMessage(function (Email $message): void {
                    $logo = (new DataPart(
                        new File(public_path('images/al-hidayah-logo.png')),
                        'al-hidayah-logo.png',
                        'image/png',
                    ))->asInline()->setContentId('school-logo@rekap-absensi.local');

                    $message->addPart($logo);
                    $html = $message->getHtmlBody();

                    if ($html === null) {
                        throw new \LogicException('Password reset email must contain an HTML body.');
                    }

                    $message->html(str_replace('cid:school-logo', 'cid:'.$logo->getContentId(), $html));
                });
        });
    }
}
