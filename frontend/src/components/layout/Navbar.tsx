'use client';

import Link from 'next/link';
import { useAuthStore } from '@/store/authStore';
import { ShoppingBag, Wallet, LogOut, User } from 'lucide-react';

export const Navbar = () => {
  const { isAuthenticated, user, logout } = useAuthStore();

  return (
    <nav className="bg-white shadow-md">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16 items-center">
          {/* Logo */}
          <Link href="/" className="flex items-center gap-2">
            <ShoppingBag className="h-8 w-8 text-blue-600" />
            <span className="text-2xl font-bold text-gray-900">T-Trade</span>
          </Link>

          {/* Navigation */}
          <div className="flex items-center gap-6">
            <Link href="/marketplace" className="text-gray-700 hover:text-blue-600">
              Marketplace
            </Link>

            {isAuthenticated ? (
              <>
                <Link href="/dashboard" className="text-gray-700 hover:text-blue-600">
                  Dashboard
                </Link>
                <Link href="/wallet" className="flex items-center gap-1 text-gray-700 hover:text-blue-600">
                  <Wallet className="h-5 w-5" />
                  Wallet
                </Link>
                <Link href="/profile" className="flex items-center gap-1 text-gray-700 hover:text-blue-600">
                  <User className="h-5 w-5" />
                  {user?.full_name}
                </Link>
                <button
                  onClick={logout}
                  className="flex items-center gap-1 text-red-600 hover:text-red-700"
                >
                  <LogOut className="h-5 w-5" />
                  Logout
                </button>
              </>
            ) : (
              <>
                <Link href="/login" className="text-gray-700 hover:text-blue-600">
                  Login
                </Link>
                <Link
                  href="/register"
                  className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                >
                  Sign Up
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
    </nav>
  );
};
