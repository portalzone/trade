'use client';

import { useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import Link from 'next/link';

export default function Navbar() {
  const router = useRouter();
  const pathname = usePathname();
  const [user, setUser] = useState<any>(null);
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      setUser(JSON.parse(storedUser));
    }
  }, []);

  const handleLogout = () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    router.push('/');
  };

  // Don't show navbar on login/register pages
  if (!user || pathname === '/login' || pathname === '/register') {
    return null;
  }

  const isActive = (path: string) => pathname === path;

  return (
    <nav className="bg-white shadow-md sticky top-0 z-40">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex justify-between items-center h-16">
          {/* Logo */}
          <Link href="/dashboard" className="flex items-center space-x-2">
            <span className="text-2xl font-bold bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">
              T-Trade
            </span>
          </Link>

          {/* Desktop Navigation */}
          <div className="hidden md:flex items-center space-x-1">
            <Link href="/marketplace" className={`px-4 py-2 rounded-lg font-medium transition ${isActive('/marketplace') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}>
              🛍️ Marketplace
            </Link>
            <Link href="/orders/my-orders" className={`px-4 py-2 rounded-lg font-medium transition ${isActive('/orders/my-orders') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}>
              📦 My Orders
            </Link>
            <Link href="/wallet" className={`px-4 py-2 rounded-lg font-medium transition ${isActive('/wallet') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}>
              💰 Wallet
            </Link>
            <Link href="/orders/create" className={`px-4 py-2 rounded-lg font-medium transition ${isActive('/orders/create') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}>
              ➕ Create Order
            </Link>
            {user?.user_type === 'ADMIN' && (
              <Link href="/admin/disputes" className={`px-4 py-2 rounded-lg font-medium transition ${isActive('/admin/disputes') ? 'bg-red-600 text-white' : 'text-red-600 hover:bg-red-50'}`}>
                ⚖️ Admin
              </Link>
            )}
          </div>

          {/* User Menu */}
          <div className="hidden md:flex items-center space-x-4">
            <div className="text-right">
              <p className="text-sm font-semibold text-gray-900">{user?.full_name}</p>
              <p className="text-xs text-gray-500">{user?.user_type}</p>
            </div>
            <button onClick={handleLogout} className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 font-medium transition">
              Logout
            </button>
          </div>

          {/* Mobile Menu Button */}
          <button onClick={() => setIsOpen(!isOpen)} className="md:hidden p-2 rounded-lg hover:bg-gray-100">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              {isOpen ? (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              ) : (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              )}
            </svg>
          </button>
        </div>

        {/* Mobile Menu */}
        {isOpen && (
          <div className="md:hidden py-4 border-t">
            <div className="flex flex-col space-y-2">
              <Link href="/marketplace" className={`px-4 py-2 rounded-lg font-medium ${isActive('/marketplace') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`} onClick={() => setIsOpen(false)}>
                🛍️ Marketplace
              </Link>
              <Link href="/orders/my-orders" className={`px-4 py-2 rounded-lg font-medium ${isActive('/orders/my-orders') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`} onClick={() => setIsOpen(false)}>
                📦 My Orders
              </Link>
              <Link href="/wallet" className={`px-4 py-2 rounded-lg font-medium ${isActive('/wallet') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`} onClick={() => setIsOpen(false)}>
                💰 Wallet
              </Link>
              <Link href="/orders/create" className={`px-4 py-2 rounded-lg font-medium ${isActive('/orders/create') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`} onClick={() => setIsOpen(false)}>
                ➕ Create Order
              </Link>
              {user?.user_type === 'ADMIN' && (
                <Link href="/admin/disputes" className={`px-4 py-2 rounded-lg font-medium ${isActive('/admin/disputes') ? 'bg-red-600 text-white' : 'text-red-600 hover:bg-red-50'}`} onClick={() => setIsOpen(false)}>
                  ⚖️ Admin Panel
                </Link>
              )}
              <div className="border-t pt-2 mt-2">
                <p className="px-4 py-2 text-sm font-semibold text-gray-900">{user?.full_name}</p>
                <p className="px-4 text-xs text-gray-500 mb-2">{user?.user_type}</p>
                <button onClick={() => { handleLogout(); setIsOpen(false); }} className="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 font-medium">
                  Logout
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </nav>
  );
}
