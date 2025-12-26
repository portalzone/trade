'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';

interface Order {
  id: number;
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

export default function MarketplacePage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    
    if (!token) {
      setIsAuthenticated(false);
      setIsLoading(false);
      return;
    }

    setIsAuthenticated(true);
    fetchOrders(token);
  }, []);

  const fetchOrders = async (token: string) => {
    try {
      const response = await fetch('http://localhost:8000/api/orders', {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });

      const result = await response.json();

      if (result.success) {
        // Handle Laravel pagination: data.data.data
        const orderData = result.data?.data || [];
        setOrders(orderData);
      } else {
        setError(result.error || result.message || 'Failed to load orders');
      }
    } catch (error) {
      setError('Connection error. Is the backend running?');
      console.error('Fetch error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading marketplace...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md text-center">
          <h2 className="text-2xl font-bold mb-4">Login Required</h2>
          <p className="text-gray-600 mb-6">Please login to browse the marketplace</p>
          <div className="flex gap-4 justify-center">
            <Link href="/login" className="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
              Login
            </Link>
            <Link href="/register" className="bg-gray-200 text-gray-800 px-6 py-3 rounded-lg hover:bg-gray-300 font-semibold">
              Sign Up
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md">
          <p className="text-red-800">❌ {error}</p>
          <button onClick={() => window.location.reload()} className="mt-4 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
            Try Again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex justify-between items-center mb-8">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Marketplace</h1>
            <p className="text-gray-600 mt-2">Browse all available orders</p>
          </div>
          <Link href="/orders/create" className="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold transition">
            + Create Order
          </Link>
        </div>

        {orders.length === 0 ? (
          <div className="bg-white rounded-lg shadow-md p-12 text-center">
            <p className="text-gray-500 text-lg">No orders available yet</p>
            <p className="text-gray-400 mt-2">Be the first to create one!</p>
            <Link href="/orders/create" className="inline-block mt-4 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
              Create Order
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {orders.map((order) => (
              <div key={order.id} className="bg-white rounded-lg shadow-md hover:shadow-lg transition overflow-hidden">
                <div className="p-6">
                  <div className="flex justify-between items-start mb-3">
                    <h3 className="text-xl font-bold text-gray-900 flex-1 mr-2">{order.title}</h3>
                    <span className={`px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap ${
                      order.order_status === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    }`}>
                      {order.order_status}
                    </span>
                  </div>

                  <p className="text-gray-600 text-sm mb-4 line-clamp-2">{order.description}</p>

                  <div className="border-t pt-4">
                    <div className="mb-3">
                      <span className="text-3xl font-bold text-blue-600">
                        ₦{parseFloat(order.price).toLocaleString()}
                      </span>
                    </div>

                    <div className="text-sm text-gray-500 mb-4">
                      <p className="font-medium">Seller: {order.seller.full_name}</p>
                      <p className="text-xs mt-1">Listed {new Date(order.created_at).toLocaleDateString()}</p>
                    </div>

                    <Link href={`/marketplace/${order.id}`} className="block w-full bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 font-semibold transition">
                      View Details
                    </Link>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
