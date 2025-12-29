'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { useCartStore } from '@/store/cartStore';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { 
  ShoppingCart,
  Trash2,
  Plus,
  Minus,
  ArrowRight,
  Package,
  ShoppingBag
} from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import Link from 'next/link';
import toast from 'react-hot-toast';

export default function CartPage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const [mounted, setMounted] = useState(false);
  
  // Get cart state
  const items = useCartStore((state) => state.items);
  const updateQuantity = useCartStore((state) => state.updateQuantity);
  const removeItem = useCartStore((state) => state.removeItem);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
    }
  }, [mounted, user, router]);

  const handleQuantityChange = (productId: number, newQuantity: number, maxStock: number) => {
    if (newQuantity < 1) {
      return;
    }
    if (newQuantity > maxStock) {
      toast.error(`Only ${maxStock} items available in stock`);
      return;
    }
    updateQuantity(productId, newQuantity);
  };

  const handleRemove = (productId: number, productName: string) => {
    removeItem(productId);
    toast.success(`${productName} removed from cart`);
  };

  // Calculate totals
  const totalPrice = items.reduce(
    (total, item) => total + parseFloat(item.price) * item.quantity,
    0
  );
  const platformFee = totalPrice * 0.025;
  const finalTotal = totalPrice + platformFee;

  if (!mounted) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!user) {
    return null;
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center space-x-3 mb-2">
            <ShoppingCart className="h-8 w-8 text-blue-600" />
            <h1 className="text-3xl font-bold text-gray-900">Shopping Cart</h1>
          </div>
          <p className="text-gray-600">
            {items.length === 0 ? 'Your cart is empty' : `${items.length} item${items.length > 1 ? 's' : ''} in your cart`}
          </p>
        </div>

        {items.length === 0 ? (
          <Card>
            <CardContent className="py-20">
              <div className="text-center">
                <ShoppingCart className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  Your cart is empty
                </h3>
                <p className="text-gray-600 mb-6">
                  Browse our marketplace and add some products to your cart
                </p>
                <Link href="/marketplace">
                  <button className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center space-x-2 mx-auto">
                    <ShoppingBag className="h-5 w-5" />
                    <span>Browse Marketplace</span>
                  </button>
                </Link>
              </div>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Cart Items */}
            <div className="lg:col-span-2 space-y-4">
              {items.map((item) => (
                <Card key={item.id} className="hover:shadow-lg transition">
                  <CardContent className="p-6">
                    <div className="flex items-start space-x-4">
                      {/* Product Image Placeholder */}
                      <div className="h-24 w-24 bg-gradient-to-br from-blue-100 to-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <Package className="h-12 w-12 text-gray-400" />
                      </div>

                      {/* Product Details */}
                      <div className="flex-1 min-w-0">
                        <Link href={`/marketplace/${item.id}`}>
                          <h3 className="text-lg font-semibold text-gray-900 hover:text-blue-600 transition mb-1">
                            {item.name}
                          </h3>
                        </Link>
                        <p className="text-sm text-gray-600 mb-3">
                          Sold by: {item.seller_name}
                        </p>

                        <div className="flex items-center justify-between">
                          {/* Quantity Controls */}
                          <div className="flex items-center space-x-3">
                            <button
                              onClick={() => handleQuantityChange(item.id, item.quantity - 1, item.stock_quantity)}
                              className="p-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                            >
                              <Minus className="h-4 w-4 text-gray-600" />
                            </button>
                            <span className="text-lg font-semibold text-gray-900 w-12 text-center">
                              {item.quantity}
                            </span>
                            <button
                              onClick={() => handleQuantityChange(item.id, item.quantity + 1, item.stock_quantity)}
                              className="p-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                            >
                              <Plus className="h-4 w-4 text-gray-600" />
                            </button>
                            <span className="text-sm text-gray-500">
                              ({item.stock_quantity} available)
                            </span>
                          </div>

                          {/* Price */}
                          <div className="text-right">
                            <p className="text-lg font-bold text-gray-900">
                              {formatCurrency((parseFloat(item.price) * item.quantity).toString())}
                            </p>
                            <p className="text-sm text-gray-600">
                              {formatCurrency(item.price)} each
                            </p>
                          </div>
                        </div>
                      </div>

                      {/* Remove Button */}
                      <button
                        onClick={() => handleRemove(item.id, item.name)}
                        className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                      >
                        <Trash2 className="h-5 w-5" />
                      </button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>

            {/* Order Summary */}
            <div className="lg:col-span-1">
              <Card className="sticky top-24">
                <CardContent className="p-6">
                  <h2 className="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>

                  <div className="space-y-3 mb-6">
                    <div className="flex justify-between text-gray-700">
                      <span>Subtotal ({items.reduce((sum, item) => sum + item.quantity, 0)} items)</span>
                      <span className="font-semibold">{formatCurrency(totalPrice.toString())}</span>
                    </div>
                    <div className="flex justify-between text-gray-700">
                      <span>Platform Fee (2.5%)</span>
                      <span className="font-semibold">{formatCurrency(platformFee.toString())}</span>
                    </div>
                    <div className="border-t pt-3 flex justify-between text-lg font-bold text-gray-900">
                      <span>Total</span>
                      <span>{formatCurrency(finalTotal.toString())}</span>
                    </div>
                  </div>

                  <Link href="/checkout">
                    <button className="w-full px-6 py-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center space-x-2 mb-4">
                      <span>Proceed to Checkout</span>
                      <ArrowRight className="h-5 w-5" />
                    </button>
                  </Link>

                  <Link href="/marketplace">
                    <button className="w-full px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold">
                      Continue Shopping
                    </button>
                  </Link>

                  <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p className="text-sm text-green-800 font-medium mb-2">
                      🔒 Escrow Protected
                    </p>
                    <p className="text-sm text-green-700">
                      Your payment is secured until you confirm delivery
                    </p>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
