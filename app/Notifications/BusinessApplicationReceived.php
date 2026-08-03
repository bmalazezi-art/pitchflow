<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessApplicationReceived extends Notification
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
            ->subject(__('messages.business_application_received_subject', [], $locale))
            ->greeting(__('messages.business_email_greeting', ['name' => $notifiable->name], $locale))
            ->line(__('messages.business_application_received_thanks', [], $locale))
            ->line(__('messages.business_application_received_review', [], $locale))
            ->line(__('messages.business_email_business_label', [], $locale))
            ->line($this->organization->name)
            ->line(__('messages.business_email_status_label', [], $locale))
            ->line(__('messages.business_application_pending_status', [], $locale))
            ->line(__('messages.business_application_received_notify', [], $locale))
            ->salutation(__('messages.business_email_salutation', [], $locale));
    }
}
