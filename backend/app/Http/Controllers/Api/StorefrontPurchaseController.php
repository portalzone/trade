<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorefrontProduct;
use App\Models\Order;
use App\Models\EscrowLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StorefrontPurchaseController extends Controller
{
    /**
     * Purchase a storefront product (create order + lock in escrow)
     */
    public function purchaseProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:storefront_products,id',
            'quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string',
            'delivery_city' => 'required|string',
            'delivery_state' => 'required|string',
            'delivery_country' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $buyer = $request->user();

        try {
            DB::beginTransaction();

            // Get the product
            $product = StorefrontProduct::with('storefront')->findOrFail($request->product_id);

            // Check if buyer is trying to buy their own product
            if ($product->storefront->user_id === $buyer->id) {
                throw new \Exception('Cannot purchase your own product');
            }

            // Check stock
            if ($product->stock_quantity < $request->quantity) {
                throw new \Exception('Insufficient stock available');
            }

            // Calculate total
            $unitPrice = $product->price;
            $quantity = $request->quantity;
            $totalPrice = $unitPrice * $quantity;
            $platformFee = $totalPrice * 0.025; // 2.5% platform fee

            // Check buyer wallet balance
            $buyerWallet = $buyer->wallet;
            if ($buyerWallet->available_balance < $totalPrice) {
                throw new \Exception('Insufficient wallet balance');
            }

            // Create the order
            $order = Order::create([
                'seller_id' => $product->storefront->user_id,
                'buyer_id' => $buyer->id,
                'title' => $product->name,
                'description' => $request->notes ?? "Order for {$product->name}",
                'price' => $unitPrice,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'currency' => 'NGN',
                'order_status' => 'IN_ESCROW',
                'delivery_address' => $request->delivery_address,
                'delivery_city' => $request->delivery_city,
                'delivery_state' => $request->delivery_state,
                'delivery_country' => $request->delivery_country,
                'escrow_locked_at' => now(),
            ]);

            // Lock funds in escrow using wallet transactions
            DB::table('wallets')
                ->where('id', $buyerWallet->id)
                ->update([
                    'available_balance' => DB::raw("available_balance - {$totalPrice}"),
                    'locked_escrow_funds' => DB::raw("locked_escrow_funds + {$totalPrice}"),
                    'updated_at' => now(),
                ]);

            // Create escrow lock record with correct column names
            EscrowLock::create([
                'order_id' => $order->id,
                'wallet_id' => $buyerWallet->id,  // Changed from buyer_wallet_id
                'amount' => $totalPrice,
                'platform_fee' => $platformFee,
                'lock_type' => 'ORDER_PAYMENT',
                'locked_at' => now(),
            ]);

            // Create ledger entry
            DB::table('ledger_entries')->insert([
                'transaction_id' => \Illuminate\Support\Str::uuid(),
                'wallet_id' => $buyerWallet->id,
                'type' => 'DEBIT',
                'amount' => $totalPrice,
                'description' => "Funds locked in escrow for order #{$order->id}",
                'reference_table' => 'orders',
                'reference_id' => $order->id,
                'created_at' => now(),
            ]);

            // Reduce product stock
            $product->decrement('stock_quantity', $quantity);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product purchased successfully',
                'data' => $order->fresh(),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase product',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
