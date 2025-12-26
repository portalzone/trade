<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class OrderPurchasedNotification extends Notification
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
                ->subject('Order Purchased!')
                ->greeting("Hello {$notifiable->full_name}!")
                ->line("Your order has been purchased: {$this->order->title}")
                ->line("Amount: N" . number_format($this->order->price, 2))
                ->line("Buyer: {$this->order->buyer->full_name}")
                ->line("Payment held in escrow. Please deliver the item.");
        }
        return (new MailMessage)
            ->subject('Purchase Confirmed')
            ->greeting("Hello {$notifiable->full_name}!")
            ->line("Purchase confirmed: {$this->order->title}")
            ->line("Amount: N" . number_format($this->order->price, 2))
            ->line("Seller: {$this->order->seller->full_name}")
            ->line("Payment locked in escrow for your protection.");
    }
    public function toArray($notifiable): array { return ['order_id' => $this->order->id]; }
}
