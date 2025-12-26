'use client';

import { useEffect, useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';

interface Order {
  id: number;
  seller_id: number;
  buyer_id: number | null;
  title: string;
  description: string;
  price: string;
  currency: string;
  order_status: string;
  created_at: string;
  seller: {
    id: number;
    full_name: string;
    email: string;
  };
}

export default function OrderDetailsPage() {
  const router = useRouter();
  const params = useParams();
  const orderId = params.id;

  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isPurchasing, setIsPurchasing] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [currentUser, setCurrentUser] = useState<any>(null);

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    const user = localStorage.getItem('user');
    
    if (!token) {
      router.push('/login');
      return;
    }

    if (user) {
      setCurrentUser(JSON.parse(user));
    }

    fetchOrder(token);
  }, [orderId, router]);

  const fetchOrder = async (token: string) => {
    try {
      const response = await fetch(`http://localhost:8000/api/orders/${orderId}`, {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });

      const data = await response.json();

      if (data.success) {
        // API returns data.data.order, not data.data
        setOrder(data.data.order);
      } else {
        setError(data.error || 'Order not found');
      }
    } catch (error) {
      setError('Failed to load order');
      console.error('Fetch error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handlePurchase = async () => {
    if (!confirm('Confirm purchase? Payment will be held in escrow until you confirm delivery.')) {
      return;
    }

    setIsPurchasing(true);
    setMessage('');

    const token = localStorage.getItem('auth_token');

    try {
      const response = await fetch(`http://localhost:8000/api/orders/${orderId}/purchase`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });

      const data = await response.json();

      if (data.success) {
        setMessage('✅ Purchase successful! Payment locked in escrow.');
        setTimeout(() => {
          router.push('/dashboard');
        }, 2000);
      } else {
        setMessage('❌ ' + (data.error || data.message || 'Purchase failed'));
      }
    } catch (error) {
      setMessage('❌ Connection error');
      console.error('Purchase error:', error);
    } finally {
      setIsPurchasing(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading order...</p>
        </div>
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md text-center">
          <p className="text-red-800 mb-4">❌ {error || 'Order not found'}</p>
          <Link href="/marketplace" className="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
            Back to Marketplace
          </Link>
        </div>
      </div>
    );
  }

  const isSeller = currentUser?.id === order.seller_id;
  const canPurchase = !isSeller && order.order_status === 'ACTIVE' && !order.buyer_id;

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4">
        <div className="mb-6">
          <Link href="/marketplace" className="text-blue-600 hover:underline">
            ← Back to Marketplace
          </Link>
        </div>

        {message && (
          <div className={`mb-6 p-4 rounded ${message.includes('✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
            {message}
          </div>
        )}

        <div className="bg-white rounded-lg shadow-lg overflow-hidden">
          <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-8">
            <div className="flex justify-between items-start">
              <div>
                <h1 className="text-3xl font-bold mb-2">{order.title}</h1>
                <p className="text-blue-100">Listed {new Date(order.created_at).toLocaleDateString()}</p>
              </div>
              <span className={`px-4 py-2 rounded-full text-sm font-semibold ${order.order_status === 'ACTIVE' ? 'bg-green-500' : 'bg-gray-500'}`}>
                {order.order_status}
              </span>
            </div>
          </div>

          <div className="p-8">
            <div className="grid md:grid-cols-3 gap-8">
              <div className="md:col-span-2 space-y-6">
                <div>
                  <h2 className="text-lg font-semibold mb-3">Description</h2>
                  <p className="text-gray-700">{order.description}</p>
                </div>

                <div className="border-t pt-6">
                  <h2 className="text-lg font-semibold mb-3">Seller Information</h2>
                  <div className="bg-gray-50 rounded-lg p-4">
                    <p className="font-semibold text-gray-900">{order.seller.full_name}</p>
                    <p className="text-sm text-gray-600">{order.seller.email}</p>
                  </div>
                </div>

                {isSeller && (
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p className="text-blue-900 font-semibold">📦 This is your order</p>
                    <p className="text-sm text-blue-700 mt-1">Buyers can purchase this item. You'll receive payment after delivery confirmation.</p>
                  </div>
                )}
              </div>

              <div className="space-y-6">
                <div className="bg-gray-50 rounded-lg p-6">
                  <p className="text-sm text-gray-600 mb-2">Price</p>
                  <p className="text-4xl font-bold text-blue-600">₦{parseFloat(order.price).toLocaleString()}</p>
                </div>

                {canPurchase && (
                  <div className="space-y-4">
                    <button onClick={handlePurchase} disabled={isPurchasing} className="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold transition disabled:opacity-50">
                      {isPurchasing ? 'Processing...' : 'Purchase Now'}
                    </button>

                    <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                      <h3 className="font-semibold text-green-900 mb-2">🔒 Escrow Protection</h3>
                      <ul className="text-xs text-green-800 space-y-1">
                        <li>• Payment held securely</li>
                        <li>• Released after delivery</li>
                        <li>• Dispute resolution available</li>
                        <li>• Platform fee: 2.5%</li>
                      </ul>
                    </div>
                  </div>
                )}

                {isSeller && (
                  <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p className="text-sm text-yellow-800">
                      <strong>Net Amount:</strong><br />₦{(parseFloat(order.price) * 0.975).toLocaleString()}<br />
                      <span className="text-xs">(After 2.5% fee)</span>
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
