<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the seller user
$seller = App\Models\User::where('email', 'support@basepan.com')->first();

if (!$seller) {
    echo "❌ Seller not found!\n";
    exit;
}

// Create storefront if not exists
$storefront = $seller->storefront;
if (!$storefront) {
    $storefront = App\Models\Storefront::create([
        'user_id' => $seller->id,
        'name' => 'Tech Haven Store',
        'slug' => 'tech-haven',
        'description' => 'Your one-stop shop for quality tech products',
        'is_active' => true,
        'total_sales' => 0,
        'total_products' => 0,
        'average_rating' => 0,
    ]);
    echo "✅ Storefront created: {$storefront->name}\n";
} else {
    echo "✅ Using existing storefront: {$storefront->name}\n";
}

// Create Electronics category using ProductCategory model
$category = App\Models\ProductCategory::firstOrCreate(
    ['storefront_id' => $storefront->id, 'slug' => 'electronics'],
    [
        'name' => 'Electronics',
        'description' => 'Latest electronic gadgets and accessories',
        'is_active' => true,
        'display_order' => 1,
    ]
);

echo "✅ Category: {$category->name}\n";

// Create demo products
$products = [
    [
        'name' => 'Wireless Bluetooth Headphones',
        'description' => 'Premium noise-cancelling wireless headphones with 30-hour battery life. Crystal clear sound quality with deep bass.',
        'price' => 25000.00,
        'stock_quantity' => 50,
    ],
    [
        'name' => 'Smartphone Power Bank 20000mAh',
        'description' => 'Fast charging power bank with dual USB ports. Compatible with all smartphones and tablets.',
        'price' => 15000.00,
        'stock_quantity' => 100,
    ],
    [
        'name' => 'USB-C Charging Cable (3-Pack)',
        'description' => 'Durable braided charging cables. Fast charging support. 6ft length.',
        'price' => 5000.00,
        'stock_quantity' => 200,
    ],
    [
        'name' => 'Laptop Backpack with USB Port',
        'description' => 'Water-resistant laptop backpack with built-in USB charging port. Fits up to 15.6 inch laptops.',
        'price' => 18000.00,
        'stock_quantity' => 30,
    ],
    [
        'name' => 'Wireless Gaming Mouse',
        'description' => 'RGB gaming mouse with adjustable DPI settings. Ergonomic design for long gaming sessions.',
        'price' => 12000.00,
        'stock_quantity' => 75,
    ],
    [
        'name' => 'Mechanical Keyboard RGB',
        'description' => 'Premium mechanical keyboard with customizable RGB lighting. Blue switches for tactile feedback.',
        'price' => 35000.00,
        'stock_quantity' => 20,
    ],
    [
        'name' => 'Phone Camera Lens Kit',
        'description' => '3-in-1 lens kit: Wide angle, macro, and fisheye. Universal clip fits all smartphones.',
        'price' => 8000.00,
        'stock_quantity' => 60,
    ],
    [
        'name' => 'Portable Bluetooth Speaker',
        'description' => 'Waterproof Bluetooth speaker with 360° sound. 12-hour battery life. Perfect for outdoor activities.',
        'price' => 20000.00,
        'stock_quantity' => 40,
    ],
];

foreach ($products as $productData) {
    $product = App\Models\StorefrontProduct::create([
        'storefront_id' => $storefront->id,
        'category_id' => $category->id,
        'name' => $productData['name'],
        'slug' => Illuminate\Support\Str::slug($productData['name']),
        'description' => $productData['description'],
        'price' => $productData['price'],
        'stock_quantity' => $productData['stock_quantity'],
        'is_active' => true,
        'published_at' => now(),
        'average_rating' => rand(35, 50) / 10,
        'reviews_count' => rand(5, 50),
        'sales_count' => rand(10, 100),
        'views_count' => rand(50, 500),
    ]);
    
    echo "✅ Product created: {$product->name}\n";
}

// Update storefront total products
$storefront->update([
    'total_products' => $storefront->products()->count(),
]);

echo "\n";
echo "═══════════════════════════════════════════\n";
echo "🎉 DEMO PRODUCTS CREATED SUCCESSFULLY!\n";
echo "═══════════════════════════════════════════\n";
echo "Storefront: {$storefront->name}\n";
echo "Category: {$category->name}\n";
echo "Products: " . count($products) . "\n";
