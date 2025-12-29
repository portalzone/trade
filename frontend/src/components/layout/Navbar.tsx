'use client';

import { useEffect, useState } from 'react';
import { useAuthStore } from '@/store/authStore';
import { useCartStore } from '@/store/cartStore';
import { 
  LayoutDashboard, 
  ShoppingBag, 
  Store,
  Package,
  Wallet,
  LogOut,
  Settings,
  ChevronDown,
  ShoppingCart
} from 'lucide-react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';

export function Navbar() {
  const { user, logout } = useAuthStore();
  const router = useRouter();
  const [showDropdown, setShowDropdown] = useState(false);
  const [mounted, setMounted] = useState(false);
  
  // Get cart items count
  const cartItems = useCartStore((state) => state.items);
  const cartItemsCount = cartItems.reduce((total, item) => total + item.quantity, 0);

  useEffect(() => {
    setMounted(true);
  }, []);

  const handleLogout = () => {
    logout();
    router.push('/login');
  };

  return (
    <nav className="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          <Link href="/dashboard" className="flex items-center space-x-2">
            <div className="h-8 w-8 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
              <span className="text-white font-bold text-lg">T</span>
            </div>
            <span className="text-xl font-bold text-gray-900">T-Trade</span>
          </Link>

          {user && (
            <div className="hidden md:flex items-center space-x-1">
              <Link href="/dashboard">
                <button className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                  <LayoutDashboard className="h-4 w-4" />
                  <span>Dashboard</span>
                </button>
              </Link>

              <Link href="/marketplace">
                <button className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                  <ShoppingBag className="h-4 w-4" />
                  <span>Marketplace</span>
                </button>
              </Link>

              {user.user_type === 'SELLER' && (
                <Link href="/my-store">
                  <button className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                    <Store className="h-4 w-4" />
                    <span>My Store</span>
                  </button>
                </Link>
              )}

              <Link href="/orders/my-orders">
                <button className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                  <Package className="h-4 w-4" />
                  <span>Orders</span>
                </button>
              </Link>

              <Link href="/wallet">
                <button className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                  <Wallet className="h-4 w-4" />
                  <span>Wallet</span>
                </button>
              </Link>

              {/* Cart Icon with Badge */}
              <Link href="/cart">
                <button className="relative flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                  <ShoppingCart className="h-4 w-4" />
                  <span>Cart</span>
                  {mounted && cartItemsCount > 0 && (
                    <span className="absolute -top-1 -right-1 h-5 w-5 bg-blue-600 text-white text-xs font-bold rounded-full flex items-center justify-center">
                      {cartItemsCount}
                    </span>
                  )}
                </button>
              </Link>
            </div>
          )}

          {user ? (
            <div className="relative">
              <button
                onClick={() => setShowDropdown(!showDropdown)}
                className="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition"
              >
                <div className="h-8 w-8 bg-gradient-to-br from-blue-600 to-purple-600 rounded-full flex items-center justify-center">
                  <span className="text-white font-semibold text-sm">
                    {user.full_name.charAt(0).toUpperCase()}
                  </span>
                </div>
                <div className="hidden md:block text-left">
                  <p className="text-sm font-semibold text-gray-900">{user.full_name}</p>
                  <p className="text-xs text-gray-500">{user.user_type}</p>
                </div>
                <ChevronDown className="h-4 w-4 text-gray-600" />
              </button>

              {showDropdown && (
                <>
                  <div
                    className="fixed inset-0 z-10"
                    onClick={() => setShowDropdown(false)}
                  />
                  <div className="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-20">
                    <div className="px-4 py-3 border-b border-gray-200">
                      <p className="text-sm font-semibold text-gray-900">{user.full_name}</p>
                      <p className="text-xs text-gray-500">{user.email}</p>
                    </div>

                    <div className="py-2">
                      <Link href="/dashboard">
                        <button className="w-full flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                          <LayoutDashboard className="h-4 w-4" />
                          <span>Dashboard</span>
                        </button>
                      </Link>

                      {user.user_type === 'SELLER' && (
                        <Link href="/my-store">
                          <button className="w-full flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                            <Store className="h-4 w-4" />
                            <span>My Store</span>
                          </button>
                        </Link>
                      )}

                      <Link href="/orders/my-orders">
                        <button className="w-full flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                          <Package className="h-4 w-4" />
                          <span>Orders</span>
                        </button>
                      </Link>

                      <Link href="/wallet">
                        <button className="w-full flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                          <Wallet className="h-4 w-4" />
                          <span>Wallet</span>
                        </button>
                      </Link>

                      <Link href="/cart">
                        <button className="w-full flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                          <ShoppingCart className="h-4 w-4" />
                          <span>Cart {mounted && cartItemsCount > 0 && `(${cartItemsCount})`}</span>
                        </button>
                      </Link>

                      <Link href="/settings">
                        <button className="w-full flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                          <Settings className="h-4 w-4" />
                          <span>Settings</span>
                        </button>
                      </Link>
                    </div>

                    <div className="border-t border-gray-200 py-2">
                      <button
                        onClick={handleLogout}
                        className="w-full flex items-center space-x-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition"
                      >
                        <LogOut className="h-4 w-4" />
                        <span>Logout</span>
                      </button>
                    </div>
                  </div>
                </>
              )}
            </div>
          ) : (
            <div className="flex items-center space-x-4">
              <Link href="/login">
                <button className="px-4 py-2 text-gray-700 hover:text-gray-900">
                  Login
                </button>
              </Link>
              <Link href="/register">
                <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                  Sign Up
                </button>
              </Link>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
}
