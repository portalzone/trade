'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState<any>(null);

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    const token = localStorage.getItem('auth_token');
    if (!token || !storedUser) {
      router.push('/login');
      return;
    }
    setUser(JSON.parse(storedUser));
  }, [router]);

  const handleLogout = () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    router.push('/');
  };

  if (!user) return <div className="min-h-screen flex items-center justify-center">Loading...</div>;

  return (
    <div className="min-h-screen bg-gray-50 py-12 px-4">
      <div className="max-w-6xl mx-auto">
        <div className="bg-white rounded-lg shadow-lg p-8">
          <div className="flex justify-between items-center mb-6">
            <h1 className="text-3xl font-bold">Dashboard</h1>
            <button onClick={handleLogout} className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
              Logout
            </button>
          </div>

          <div className="border-t pt-6 mb-8">
            <h2 className="text-xl font-semibold mb-4">Welcome, {user.full_name}! 🎉</h2>
            
            <div className="grid grid-cols-2 gap-4">
              <div className="bg-gray-50 p-4 rounded-lg">
                <p className="text-sm text-gray-600">Email</p>
                <p className="font-semibold">{user.email}</p>
              </div>
              <div className="bg-gray-50 p-4 rounded-lg">
                <p className="text-sm text-gray-600">Username</p>
                <p className="font-semibold">{user.username}</p>
              </div>
              <div className="bg-gray-50 p-4 rounded-lg">
                <p className="text-sm text-gray-600">Type</p>
                <p className="font-semibold">{user.user_type}</p>
              </div>
              <div className="bg-gray-50 p-4 rounded-lg">
                <p className="text-sm text-gray-600">Phone</p>
                <p className="font-semibold">{user.phone_number}</p>
              </div>
            </div>
          </div>

          {/* Quick Links */}
          <div className="grid md:grid-cols-4 gap-4">
            <Link href="/marketplace" className="bg-blue-600 text-white p-6 rounded-lg hover:bg-blue-700 transition text-center">
              <p className="text-3xl mb-2">🛍️</p>
              <p className="font-semibold">Marketplace</p>
            </Link>
            <Link href="/wallet" className="bg-green-600 text-white p-6 rounded-lg hover:bg-green-700 transition text-center">
              <p className="text-3xl mb-2">💰</p>
              <p className="font-semibold">My Wallet</p>
            </Link>
            <Link href="/orders/my-orders" className="bg-purple-600 text-white p-6 rounded-lg hover:bg-purple-700 transition text-center">
              <p className="text-3xl mb-2">📦</p>
              <p className="font-semibold">My Orders</p>
            </Link>
            <Link href="/orders/create" className="bg-yellow-600 text-white p-6 rounded-lg hover:bg-yellow-700 transition text-center">
              <p className="text-3xl mb-2">➕</p>
              <p className="font-semibold">Create Order</p>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
