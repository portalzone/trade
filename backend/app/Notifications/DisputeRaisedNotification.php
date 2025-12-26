<?php
namespace App\Notifications;
use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class DisputeRaisedNotification extends Notification
{
    use Queueable;
    protected Dispute $dispute;
    public function __construct(Dispute $dispute) { $this->dispute = $dispute; }
    public function via($notifiable): array { return ['mail', 'database']; }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dispute Raised')
            ->greeting("Hello {$notifiable->full_name}!")
            ->line("A dispute has been raised on order: {$this->dispute->order->title}")
            ->line("Reason: {$this->dispute->dispute_reason}")
            ->line("Our team will review it shortly.");
    }
    public function toArray($notifiable): array { return ['dispute_id' => $this->dispute->id]; }
}
