'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Wallet, 
  ShoppingBag, 
  Package, 
  TrendingUp, 
  Shield,
  Store,
  Plus,
  ArrowUpRight,
  Clock,
  CheckCircle
} from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import Link from 'next/link';

export default function DashboardPage() {
  const router = useRouter();
  const { user, wallet } = useAuthStore();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
    }
  }, [mounted, user, router]);

  if (!mounted || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  const quickActions = user.user_type === 'SELLER' 
    ? [
        { icon: Plus, label: 'Add Product', href: '/products/create', color: 'blue' },
        { icon: Store, label: 'My Store', href: '/storefront', color: 'purple' },
        { icon: Package, label: 'Orders', href: '/orders/my-orders', color: 'green' },
        { icon: TrendingUp, label: 'Analytics', href: '/analytics', color: 'orange' },
      ]
    : [
        { icon: ShoppingBag, label: 'Browse Market', href: '/marketplace', color: 'blue' },
        { icon: Package, label: 'My Orders', href: '/orders/my-orders', color: 'purple' },
        { icon: Wallet, label: 'My Wallet', href: '/wallet', color: 'green' },
        { icon: Clock, label: 'Order History', href: '/orders/history', color: 'orange' },
      ];

  const tierBadge = {
    1: { label: 'Tier 1', color: 'default' as const },
    2: { label: 'Tier 2', color: 'success' as const },
    3: { label: 'Tier 3', color: 'warning' as const },
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">
              Welcome back, {user.full_name}! 👋
            </h1>
            <p className="text-gray-600 mt-1">
              Here's what's happening with your account today.
            </p>
          </div>
          <div className="mt-4 md:mt-0 flex items-center space-x-3">
            <Badge variant={tierBadge[user.kyc_tier as keyof typeof tierBadge]?.color}>
              {tierBadge[user.kyc_tier as keyof typeof tierBadge]?.label}
            </Badge>
            <Badge variant="success">
              <CheckCircle className="h-3 w-3 mr-1" />
              {user.kyc_status}
            </Badge>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* Wallet Balance */}
          <Card className="border-l-4 border-l-blue-500 hover:shadow-lg transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center justify-between">
                <span>Available Balance</span>
                <div className="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                  <Wallet className="h-5 w-5 text-blue-600" />
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-1">
                <p className="text-3xl font-bold text-gray-900">
                  {formatCurrency(wallet?.available_balance || 0)}
                </p>
                <p className="text-sm text-gray-500">
                  Locked: {formatCurrency(wallet?.locked_escrow_funds || 0)}
                </p>
              </div>
              <Link href="/wallet">
                <button className="mt-4 text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center">
                  View Wallet
                  <ArrowUpRight className="h-4 w-4 ml-1" />
                </button>
              </Link>
            </CardContent>
          </Card>

          {/* Total Balance */}
          <Card className="border-l-4 border-l-green-500 hover:shadow-lg transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center justify-between">
                <span>Total Balance</span>
                <div className="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                  <TrendingUp className="h-5 w-5 text-green-600" />
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-1">
                <p className="text-3xl font-bold text-gray-900">
                  {formatCurrency(wallet?.total_balance || 0)}
                </p>
                <p className="text-sm text-gray-500">
                  Including locked funds
                </p>
              </div>
              <Link href="/wallet">
                <button className="mt-4 text-sm text-green-600 hover:text-green-700 font-medium flex items-center">
                  Deposit Funds
                  <ArrowUpRight className="h-4 w-4 ml-1" />
                </button>
              </Link>
            </CardContent>
          </Card>

          {/* Account Status */}
          <Card className="border-l-4 border-l-purple-500 hover:shadow-lg transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center justify-between">
                <span>Account Status</span>
                <div className="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                  <Shield className="h-5 w-5 text-purple-600" />
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-1">
                <p className="text-2xl font-bold text-gray-900">
                  {user.account_status}
                </p>
                <p className="text-sm text-gray-500">
                  User Type: {user.user_type}
                </p>
              </div>
              <Link href="/settings">
                <button className="mt-4 text-sm text-purple-600 hover:text-purple-700 font-medium flex items-center">
                  Upgrade Tier
                  <ArrowUpRight className="h-4 w-4 ml-1" />
                </button>
              </Link>
            </CardContent>
          </Card>
        </div>

        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {quickActions.map((action, index) => {
                const Icon = action.icon;
                const colors = {
                  blue: 'bg-blue-100 text-blue-600 hover:bg-blue-200',
                  purple: 'bg-purple-100 text-purple-600 hover:bg-purple-200',
                  green: 'bg-green-100 text-green-600 hover:bg-green-200',
                  orange: 'bg-orange-100 text-orange-600 hover:bg-orange-200',
                };
                
                return (
                  <Link key={index} href={action.href}>
                    <div className="p-6 rounded-lg border-2 border-gray-100 hover:border-gray-300 transition cursor-pointer group">
                      <div className={`h-12 w-12 rounded-lg ${colors[action.color as keyof typeof colors]} flex items-center justify-center mb-3 group-hover:scale-110 transition`}>
                        <Icon className="h-6 w-6" />
                      </div>
                      <p className="font-semibold text-gray-900">{action.label}</p>
                    </div>
                  </Link>
                );
              })}
            </div>
          </CardContent>
        </Card>

        {/* Recent Activity Placeholder */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-center py-12">
              <Package className="h-12 w-12 text-gray-300 mx-auto mb-4" />
              <p className="text-gray-500">No recent activity</p>
              <p className="text-sm text-gray-400 mt-1">
                Your transactions will appear here
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
