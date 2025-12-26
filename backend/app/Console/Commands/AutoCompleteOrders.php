<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class AutoCompleteOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete orders that have been in escrow for 7+ days';

    protected OrderService $orderService;

    /**
     * Create a new command instance.
     */
    public function __construct(OrderService $orderService)
    {
        parent::__construct();
        $this->orderService = $orderService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $autoCompleteDays = config('escrow.auto_complete_days', 7);

        $this->info("Checking for orders in escrow for {$autoCompleteDays}+ days...");

        $orders = Order::where('order_status', 'IN_ESCROW')
            ->where('escrow_locked_at', '<=', now()->subDays($autoCompleteDays))
            ->get();

        $this->info("Found {$orders->count()} orders to auto-complete");

        $successful = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                // Complete order (system action)
                $this->orderService->autoCompleteOrder($order);
                
                $this->info("✓ Order #{$order->id} ({$order->title}) auto-completed successfully");
                $successful++;
            } catch (\Exception $e) {
                $this->error("✗ Order #{$order->id} failed: " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Auto-complete process finished");
        $this->info("Successful: {$successful}");
        
        if ($failed > 0) {
            $this->error("Failed: {$failed}");
        }

        return Command::SUCCESS;
    }
}
