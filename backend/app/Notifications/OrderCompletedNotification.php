<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class OrderCompletedNotification extends Notification
{
    use Queueable;
    protected Order $order;
    protected string $userType;
    public function __construct(Order $order, string $userType)
    {
        $this->order = $order;
        $this->userType = $userType;
    }
    public function via($notifiable): array { return ['mail', 'database']; }
    public function toMail($notifiable): MailMessage
    {
        if ($this->userType === 'seller') {
            return (new MailMessage)
                ->subject('Payment Released!')
                ->greeting("Hello {$notifiable->full_name}!")
                ->line("Payment released for: {$this->order->title}")
                ->line("Check your wallet!");
        }
        return (new MailMessage)
            ->subject('Order Completed!')
            ->greeting("Hello {$notifiable->full_name}!")
            ->line("Order completed: {$this->order->title}")
            ->line("Thank you for using T-Trade!");
    }
    public function toArray($notifiable): array { return ['order_id' => $this->order->id]; }
}
