<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('messages.employee_invitation_subject'))
            ->greeting(__('messages.employee_invitation_greeting', ['name' => $notifiable->name]))
            ->line(__('messages.employee_invitation_intro', ['organization' => $notifiable->organization?->name]))
            ->action(__('messages.employee_invitation_action'), $url)
            ->line(__('messages.employee_invitation_expiry'));
    }
}
