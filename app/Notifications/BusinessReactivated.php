<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessReactivated extends Notification
{
    public function __construct(private readonly Organization $organization) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->preferredLocale();

        return (new MailMessage)
            ->subject(__('messages.business_reactivated_subject', [], $locale))
            ->greeting(__('messages.business_email_greeting', ['name' => $notifiable->name], $locale))
            ->line(__('messages.business_reactivated_line', [], $locale))
            ->line(__('messages.business_email_business_label', [], $locale))
            ->line($this->organization->name)
            ->action(__('messages.business_approved_action', [], $locale), route('login'))
            ->salutation(__('messages.business_email_salutation', [], $locale));
    }
}
