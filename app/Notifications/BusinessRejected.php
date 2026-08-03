<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessRejected extends Notification
{
    public function __construct(
        private readonly Organization $organization,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->preferredLocale();
        $message = (new MailMessage)
            ->subject(__('messages.business_rejected_subject', [], $locale))
            ->greeting(__('messages.business_email_greeting', ['name' => $notifiable->name], $locale))
            ->line(__('messages.business_rejected_line', [], $locale))
            ->line(__('messages.business_email_business_label', [], $locale))
            ->line($this->organization->name);

        if (filled($this->reason)) {
            $message->line(__('messages.business_rejected_reason', ['reason' => $this->reason], $locale));
        }

        return $message->salutation(__('messages.business_email_salutation', [], $locale));
    }
}
