<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get users
$buyer = App\Models\User::where('email', 'contact@basepan.com')->first();
$seller = App\Models\User::where('email', 'support@basepan.com')->first();

if (!$buyer || !$seller) {
    echo "❌ Users not found!\n";
    exit;
}

echo "Creating demo orders...\n\n";

// Order templates (these are listings that sellers create)
$orderTemplates = [
    [
        'title' => 'iPhone 13 Pro 256GB - Like New',
        'description' => 'iPhone 13 Pro in excellent condition. Includes original box, charger, and accessories. No scratches, battery health 98%. Fully functional.',
        'price' => 450000.00,
        'category' => 'Electronics',
    ],
    [
        'title' => 'Gaming Laptop - ASUS ROG',
        'description' => 'High-performance gaming laptop. RTX 3060, 16GB RAM, 512GB SSD. Perfect for gaming and content creation. Used for 6 months.',
        'price' => 650000.00,
        'category' => 'Electronics',
    ],
    [
        'title' => 'Designer Handbag - Authentic',
        'description' => 'Authentic designer handbag with certificate of authenticity. Gently used, excellent condition. Comes with dust bag.',
        'price' => 85000.00,
        'category' => 'Fashion',
    ],
    [
        'title' => 'PlayStation 5 Console + 3 Games',
        'description' => 'PS5 console in perfect condition. Includes 3 popular games (FIFA 24, Spider-Man, God of War). All cables and 2 controllers included.',
        'price' => 320000.00,
        'category' => 'Electronics',
    ],
    [
        'title' => 'Samsung Smart TV 55-inch 4K',
        'description' => '55-inch 4K Smart TV with HDR. Crystal clear display, perfect for movies and gaming. Includes wall mount and remote.',
        'price' => 280000.00,
        'category' => 'Electronics',
    ],
    [
        'title' => 'Canon EOS Camera + Lenses',
        'description' => 'Professional DSLR camera with 2 lenses (50mm and 18-55mm). Perfect for photography enthusiasts. Includes camera bag.',
        'price' => 420000.00,
        'category' => 'Electronics',
    ],
    [
        'title' => 'Office Desk - Executive Style',
        'description' => 'Large executive office desk. Solid wood construction. Perfect condition. Buyer must arrange pickup.',
        'price' => 75000.00,
        'category' => 'Furniture',
    ],
    [
        'title' => 'Nike Air Jordan Sneakers - Size 42',
        'description' => 'Authentic Nike Air Jordan sneakers. Worn twice, like new condition. Original box and tags included.',
        'price' => 45000.00,
        'category' => 'Fashion',
    ],
];

$statuses = ['ACTIVE', 'IN_ESCROW', 'COMPLETED', 'COMPLETED', 'COMPLETED'];
$createdOrders = [];

foreach ($orderTemplates as $index => $template) {
    $status = $statuses[array_rand($statuses)];
    
    $orderData = [
        'seller_id' => $seller->id,
        'title' => $template['title'],
        'description' => $template['description'],
        'price' => $template['price'],
        'currency' => 'NGN',
        'category' => $template['category'],
        'order_status' => $status,
        'created_at' => now()->subDays(rand(1, 30)),
    ];
    
    // If order is purchased (not ACTIVE), assign buyer
    if ($status !== 'ACTIVE') {
        $orderData['buyer_id'] = $buyer->id;
        $orderData['escrow_locked_at'] = now()->subDays(rand(0, 15));
    }
    
    // If completed, set completion date
    if ($status === 'COMPLETED') {
        $orderData['completed_at'] = now()->subDays(rand(0, 10));
    }
    
    $order = App\Models\Order::create($orderData);
    
    // Create escrow lock for purchased orders (locks funds in buyer's wallet)
    if ($status !== 'ACTIVE') {
        $platformFee = $order->price * 0.025; // 2.5% platform fee
        
        App\Models\EscrowLock::create([
            'order_id' => $order->id,
            'wallet_id' => $buyer->wallet->id, // Buyer's wallet
            'amount' => $order->price,
            'platform_fee' => $platformFee,
            'lock_type' => 'ORDER_PAYMENT',
            'locked_at' => $orderData['escrow_locked_at'],
            'released_at' => $status === 'COMPLETED' ? $orderData['completed_at'] : null,
        ]);
    }
    
    $createdOrders[] = $order;
    
    $buyerInfo = $status === 'ACTIVE' ? 'Available' : 'Purchased by ' . $buyer->full_name;
    echo "✅ Order: {$order->title} - {$status} - " . number_format($order->price, 2) . " NGN - {$buyerInfo}\n";
}

// Create one disputed order
$disputedOrder = App\Models\Order::create([
    'seller_id' => $seller->id,
    'buyer_id' => $buyer->id,
    'title' => 'MacBook Pro 16-inch 2023',
    'description' => 'MacBook Pro with M2 chip, 16GB RAM, 512GB SSD. Claimed to be in excellent condition.',
    'price' => 980000.00,
    'currency' => 'NGN',
    'category' => 'Electronics',
    'order_status' => 'DISPUTED',
    'escrow_locked_at' => now()->subDays(10),
    'created_at' => now()->subDays(15),
]);

$platformFee = $disputedOrder->price * 0.025;

App\Models\EscrowLock::create([
    'order_id' => $disputedOrder->id,
    'wallet_id' => $buyer->wallet->id,
    'amount' => $disputedOrder->price,
    'platform_fee' => $platformFee,
    'lock_type' => 'DISPUTE_HOLD',
    'locked_at' => now()->subDays(10),
]);

App\Models\Dispute::create([
    'order_id' => $disputedOrder->id,
    'raised_by_user_id' => $buyer->id,
    'dispute_reason' => 'PRODUCT_NOT_AS_DESCRIBED', // Changed from reason
    'description' => 'The MacBook has significant scratches and battery health is only 75%, not as described in the listing.',
    'status' => 'OPEN',
    'opened_at' => now()->subDays(3),
]);

echo "✅ Order: {$disputedOrder->title} - DISPUTED - " . number_format($disputedOrder->price, 2) . " NGN\n";

echo "\n";
echo "═══════════════════════════════════════════\n";
echo "🎉 DEMO ORDERS CREATED SUCCESSFULLY!\n";
echo "═══════════════════════════════════════════\n";
echo "Total Orders: " . (count($createdOrders) + 1) . "\n";
echo "Buyer: {$buyer->email}\n";
echo "Seller: {$seller->email}\n";
echo "\n";

$statusCounts = [
    'ACTIVE' => 0,
    'IN_ESCROW' => 0,
    'COMPLETED' => 0,
    'DISPUTED' => 1,
];

foreach ($createdOrders as $order) {
    $statusCounts[$order->order_status]++;
}

echo "Order Status Breakdown:\n";
foreach ($statusCounts as $status => $count) {
    echo "  - {$status}: {$count}\n";
}
