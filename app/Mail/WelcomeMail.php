<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Welcome for a new admin user ($reset = false) or a password link sent by an admin ($reset = true).
 */
class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url, public bool $reset = false) {}

    public function templateKey(): string
    {
        return $this->reset ? 'password_link' : 'welcome';
    }

    /**
     * @return array<string, string>
     */
    public function templateValues(): array
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return [
            'naam' => $this->user->name,
            'voornaam' => Str::before(trim($this->user->name), ' '),
            'email' => $this->user->email,
            'rollen' => $this->user->roles()->pluck('name')->implode(', ') ?: '-',
            'beheer_url' => url('/admin'),
            'geldig' => $minutes >= 1440 && $minutes % 1440 === 0 ? ($minutes / 1440).' '.($minutes === 1440 ? 'dag' : 'dagen') : ($minutes >= 60 && $minutes % 60 === 0 ? ($minutes / 60).' uur' : $minutes.' minuten'),
            ...MailTemplates::commonValues(),
        ];
    }

    public function envelope(): Envelope
    {
        $replyTo = settings()->get('mail.reply_to') ?: settings()->get('vzw.email');

        return new Envelope(
            subject: MailTemplates::subject($this->templateKey(), $this->templateValues()),
            replyTo: filled($replyTo) ? [new Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.template', with: ['parts' => MailTemplates::parts($this->templateKey(), $this->templateValues(), [
            'knop' => [MailTemplates::button($this->url, $this->reset ? 'Nieuw wachtwoord kiezen' : 'Wachtwoord kiezen')],
        ])]);
    }
}
