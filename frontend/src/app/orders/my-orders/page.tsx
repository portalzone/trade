'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

interface Order {
  id: number;
  seller_id: number;
  buyer_id: number | null;
  title: string;
  price: string;
  order_status: string;
  created_at: string;
  seller?: { full_name: string };
  buyer?: { full_name: string };
}

export default function MyOrdersPage() {
  const router = useRouter();
  const [sellingOrders, setSellingOrders] = useState<Order[]>([]);
  const [buyingOrders, setBuyingOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState<'selling' | 'buying'>('selling');

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    
    if (!token) {
      router.push('/login');
      return;
    }

    fetchOrders(token);
  }, [router]);

  const fetchOrders = async (token: string) => {
    try {
      const sellingRes = await fetch('http://localhost:8000/api/orders/my/selling', {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });
      const sellingData = await sellingRes.json();

      const buyingRes = await fetch('http://localhost:8000/api/orders/my/buying', {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });
      const buyingData = await buyingRes.json();

      if (sellingData.success && buyingData.success) {
        // Handle Laravel pagination: data.data.data
        setSellingOrders(sellingData.data?.data || []);
        setBuyingOrders(buyingData.data?.data || []);
      } else {
        setError('Failed to load orders');
      }
    } catch (error) {
      setError('Connection error');
      console.error('Fetch error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'ACTIVE': return 'bg-green-100 text-green-800';
      case 'PURCHASED': return 'bg-blue-100 text-blue-800';
      case 'IN_ESCROW': return 'bg-blue-100 text-blue-800';
      case 'COMPLETED': return 'bg-purple-100 text-purple-800';
      case 'DISPUTED': return 'bg-red-100 text-red-800';
      case 'CANCELLED': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading orders...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md">
          <p className="text-red-800">❌ {error}</p>
        </div>
      </div>
    );
  }

  const currentOrders = activeTab === 'selling' ? sellingOrders : buyingOrders;

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-6xl mx-auto px-4">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">My Orders</h1>
          <p className="text-gray-600 mt-2">Track your buying and selling activity</p>
        </div>

        <div className="bg-white rounded-lg shadow-md mb-6">
          <div className="border-b">
            <div className="flex">
              <button onClick={() => setActiveTab('selling')} className={`flex-1 px-6 py-4 font-semibold transition ${activeTab === 'selling' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'}`}>
                Selling ({sellingOrders.length})
              </button>
              <button onClick={() => setActiveTab('buying')} className={`flex-1 px-6 py-4 font-semibold transition ${activeTab === 'buying' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'}`}>
                Buying ({buyingOrders.length})
              </button>
            </div>
          </div>

          <div className="p-6">
            {currentOrders.length === 0 ? (
              <div className="text-center py-12">
                <p className="text-gray-500 text-lg mb-4">
                  {activeTab === 'selling' ? 'You haven\'t listed any items yet' : 'You haven\'t purchased anything yet'}
                </p>
                <Link href={activeTab === 'selling' ? '/orders/create' : '/marketplace'} className="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
                  {activeTab === 'selling' ? 'Create Order' : 'Browse Marketplace'}
                </Link>
              </div>
            ) : (
              <div className="space-y-4">
                {currentOrders.map((order) => (
                  <div key={order.id} className="border border-gray-200 rounded-lg p-6 hover:shadow-md transition">
                    <div className="flex justify-between items-start mb-4">
                      <div className="flex-1">
                        <h3 className="text-xl font-bold text-gray-900 mb-2">{order.title}</h3>
                        <div className="text-sm text-gray-600 space-y-1">
                          <p>Order ID: #{order.id}</p>
                          {activeTab === 'selling' && order.buyer && <p>Buyer: {order.buyer.full_name}</p>}
                          {activeTab === 'buying' && order.seller && <p>Seller: {order.seller.full_name}</p>}
                          <p>Created: {new Date(order.created_at).toLocaleDateString()}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <p className="text-2xl font-bold text-blue-600 mb-2">₦{parseFloat(order.price).toLocaleString()}</p>
                        <span className={`px-3 py-1 rounded-full text-xs font-semibold ${getStatusColor(order.order_status)}`}>
                          {order.order_status}
                        </span>
                      </div>
                    </div>
                    <Link href={`/marketplace/${order.id}`} className="block w-full bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 font-semibold transition">
                      View Details
                    </Link>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="grid md:grid-cols-3 gap-6">
          <div className="bg-white rounded-lg shadow-md p-6">
            <p className="text-sm text-gray-600 mb-2">Active Listings</p>
            <p className="text-3xl font-bold text-blue-600">{sellingOrders.filter(o => o.order_status === 'ACTIVE').length}</p>
          </div>
          <div className="bg-white rounded-lg shadow-md p-6">
            <p className="text-sm text-gray-600 mb-2">In Escrow</p>
            <p className="text-3xl font-bold text-yellow-600">{buyingOrders.filter(o => o.order_status === 'IN_ESCROW').length}</p>
          </div>
          <div className="bg-white rounded-lg shadow-md p-6">
            <p className="text-sm text-gray-600 mb-2">Completed</p>
            <p className="text-3xl font-bold text-green-600">{[...sellingOrders, ...buyingOrders].filter(o => o.order_status === 'COMPLETED').length}</p>
          </div>
        </div>
      </div>
    </div>
  );
}
