'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { useCartStore } from '@/store/cartStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { 
  ShoppingCart,
  Package,
  Shield,
  CheckCircle,
  ArrowLeft,
  AlertCircle
} from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import Link from 'next/link';
import toast from 'react-hot-toast';

export default function CheckoutPage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const { items, clearCart, getTotalPrice } = useCartStore();
  const [isProcessing, setIsProcessing] = useState(false);
  const [mounted, setMounted] = useState(false);
  const [wallet, setWallet] = useState<any>(null);
  const [isLoadingWallet, setIsLoadingWallet] = useState(true);

  // Delivery details
  const [deliveryAddress, setDeliveryAddress] = useState('');
  const [deliveryCity, setDeliveryCity] = useState('');
  const [deliveryState, setDeliveryState] = useState('');
  const [notes, setNotes] = useState('');

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    if (mounted && user) {
      fetchWallet();
    }
  }, [mounted, user]);

  const fetchWallet = async () => {
    setIsLoadingWallet(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/wallet', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      
      if (data.success) {
        setWallet(data.data.wallet);
      }
    } catch (error) {
      console.error('Error fetching wallet:', error);
    } finally {
      setIsLoadingWallet(false);
    }
  };

  const handlePlaceOrders = async () => {
    if (!deliveryAddress.trim() || !deliveryCity.trim() || !deliveryState.trim()) {
      toast.error('Please fill in delivery details');
      return;
    }

    if (items.length === 0) {
      toast.error('Your cart is empty');
      return;
    }

    // Check wallet balance
    const totalPrice = getTotalPrice();
    const platformFee = totalPrice * 0.025;
    const finalTotal = totalPrice + platformFee;

    if (wallet && wallet.available_balance < finalTotal) {
      toast.error('Insufficient wallet balance. Please fund your wallet first.');
      return;
    }

    setIsProcessing(true);

    try {
      const token = localStorage.getItem('auth_token');
      const successfulOrders = [];
      const failedOrders = [];

      // Purchase each product using the new endpoint
      for (const item of items) {
        try {
          const purchaseData = {
            product_id: item.id,
            quantity: item.quantity,
            delivery_address: deliveryAddress,
            delivery_city: deliveryCity,
            delivery_state: deliveryState,
            delivery_country: 'Nigeria',
            notes: notes || `Order for ${item.name}`,
          };

          console.log('Purchasing product:', purchaseData);

          const response = await fetch('http://localhost:8000/api/storefront/purchase', {
            method: 'POST',
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(purchaseData),
          });

          const data = await response.json();
          console.log('Purchase response:', data);

          if (data.success) {
            successfulOrders.push({
              orderId: data.data.id,
              productName: item.name,
            });
          } else {
            failedOrders.push({
              productName: item.name,
              error: data.message || data.error || 'Failed to purchase',
            });
            console.error('Purchase failed:', data);
          }
        } catch (error) {
          console.error(`Error purchasing ${item.name}:`, error);
          failedOrders.push({
            productName: item.name,
            error: 'Network error',
          });
        }
      }

      // Show results
      if (successfulOrders.length > 0) {
        clearCart();
        toast.success(
          `${successfulOrders.length} order(s) placed successfully! 🎉`,
          { duration: 5000 }
        );

        if (failedOrders.length > 0) {
          toast.error(
            `${failedOrders.length} order(s) failed. Check orders page for details.`,
            { duration: 5000 }
          );
        }

        // Redirect to orders page
        setTimeout(() => {
          router.push('/orders/my-orders');
        }, 1500);
      } else {
        toast.error('All orders failed. Please try again.');
        console.error('Failed orders:', failedOrders);
      }
    } catch (error) {
      console.error('Error placing orders:', error);
      toast.error('Failed to place orders. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const totalPrice = getTotalPrice();
  const platformFee = totalPrice * 0.025;
  const finalTotal = totalPrice + platformFee;

  if (!mounted || isLoadingWallet) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!user) {
    return null;
  }

  if (items.length === 0) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
        <div className="max-w-4xl mx-auto">
          <Card>
            <CardContent className="py-20">
              <div className="text-center">
                <ShoppingCart className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  Your cart is empty
                </h3>
                <p className="text-gray-600 mb-6">
                  Add some products to your cart before checking out
                </p>
                <Link href="/marketplace">
                  <button className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    Browse Marketplace
                  </button>
                </Link>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  const hasInsufficientBalance = wallet && wallet.available_balance < finalTotal;

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <Link href="/cart">
            <button className="mb-4 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
              <ArrowLeft className="h-5 w-5" />
              <span>Back to Cart</span>
            </button>
          </Link>

          <div className="flex items-center space-x-3 mb-2">
            <ShoppingCart className="h-8 w-8 text-blue-600" />
            <h1 className="text-3xl font-bold text-gray-900">Checkout</h1>
          </div>
          <p className="text-gray-600">Review your order and complete purchase</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Content */}
          <div className="lg:col-span-2 space-y-6">
            {/* Delivery Information */}
            <Card>
              <CardHeader>
                <CardTitle>Delivery Information</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Delivery Address *
                  </label>
                  <input
                    type="text"
                    value={deliveryAddress}
                    onChange={(e) => setDeliveryAddress(e.target.value)}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Enter your delivery address"
                    required
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      City *
                    </label>
                    <input
                      type="text"
                      value={deliveryCity}
                      onChange={(e) => setDeliveryCity(e.target.value)}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="City"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      State *
                    </label>
                    <input
                      type="text"
                      value={deliveryState}
                      onChange={(e) => setDeliveryState(e.target.value)}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="State"
                      required
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Order Notes (Optional)
                  </label>
                  <textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={3}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Any special instructions for your order"
                  />
                </div>
              </CardContent>
            </Card>

            {/* Order Items Review */}
            <Card>
              <CardHeader>
                <CardTitle>Order Review ({items.length} items)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {items.map((item) => (
                    <div
                      key={item.id}
                      className="flex items-start space-x-4 pb-4 border-b last:border-b-0"
                    >
                      <div className="h-16 w-16 bg-gradient-to-br from-blue-100 to-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <Package className="h-8 w-8 text-gray-400" />
                      </div>

                      <div className="flex-1 min-w-0">
                        <h4 className="font-semibold text-gray-900">{item.name}</h4>
                        <p className="text-sm text-gray-600">by {item.seller_name}</p>
                        <p className="text-sm text-gray-600">Quantity: {item.quantity}</p>
                      </div>

                      <div className="text-right">
                        <p className="font-bold text-gray-900">
                          {formatCurrency((parseFloat(item.price) * item.quantity).toString())}
                        </p>
                        <p className="text-sm text-gray-600">
                          {formatCurrency(item.price)} each
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Order Summary Sidebar */}
          <div className="lg:col-span-1">
            <Card className="sticky top-24">
              <CardHeader>
                <CardTitle>Order Summary</CardTitle>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-3">
                  <div className="flex justify-between text-gray-700">
                    <span>Subtotal</span>
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

                {/* Wallet Balance */}
                <div className="p-4 bg-gray-50 rounded-lg">
                  <div className="flex justify-between text-sm mb-1">
                    <span className="text-gray-600">Wallet Balance:</span>
                    <span className="font-semibold text-gray-900">
                      {wallet ? formatCurrency(wallet.available_balance.toString()) : '---'}
                    </span>
                  </div>
                  {hasInsufficientBalance && (
                    <div className="mt-2 p-3 bg-red-50 border border-red-200 rounded-lg">
                      <div className="flex items-start space-x-2">
                        <AlertCircle className="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" />
                        <div>
                          <p className="text-sm font-medium text-red-900">
                            Insufficient Balance
                          </p>
                          <p className="text-sm text-red-700 mt-1">
                            Please fund your wallet to complete this order
                          </p>
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* Place Order Button */}
                <button
                  onClick={handlePlaceOrders}
                  disabled={isProcessing || hasInsufficientBalance}
                  className="w-full px-6 py-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isProcessing ? (
                    <>
                      <Spinner size="sm" />
                      <span>Processing...</span>
                    </>
                  ) : (
                    <>
                      <CheckCircle className="h-5 w-5" />
                      <span>Place Order</span>
                    </>
                  )}
                </button>

                {hasInsufficientBalance && (
                  <Link href="/wallet">
                    <button className="w-full px-6 py-3 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition font-semibold">
                      Fund Wallet
                    </button>
                  </Link>
                )}

                {/* Escrow Protection Notice */}
                <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
                  <div className="flex items-start space-x-3">
                    <Shield className="h-5 w-5 text-green-600 mt-0.5 flex-shrink-0" />
                    <div>
                      <p className="text-sm font-semibold text-green-900 mb-1">
                        Escrow Protection
                      </p>
                      <p className="text-sm text-green-700">
                        Your payment will be held securely in escrow until you confirm
                        delivery of all items
                      </p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  );
}