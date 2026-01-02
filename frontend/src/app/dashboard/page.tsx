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
  TrendingUp,
  Package,
  ArrowUpRight,
  ArrowDownRight,
  Clock
} from 'lucide-react';
import Link from 'next/link';
import { formatCurrency, formatDate } from '@/lib/utils';

interface RecentActivity {
  id: number;
  type: string;
  amount: string;
  description: string;
  created_at: string;
}

export default function DashboardPage() {
  const router = useRouter();
  const { user, wallet } = useAuthStore();
  const [mounted, setMounted] = useState(false);
  const [recentActivity, setRecentActivity] = useState<RecentActivity[]>([]);
  const [isLoadingActivity, setIsLoadingActivity] = useState(true);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    if (mounted && user) {
      fetchRecentActivity();
    }
  }, [mounted, user]);

  const fetchRecentActivity = async () => {
    setIsLoadingActivity(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/wallet/transactions?limit=5', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      if (data.success) {
        setRecentActivity(data.data.transactions || []);
      }
    } catch (error) {
      console.error('Error fetching recent activity:', error);
    } finally {
      setIsLoadingActivity(false);
    }
  };

  if (!mounted || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  const getActivityIcon = (type: string) => {
    if (type.includes('CREDIT') || type.includes('DEPOSIT')) {
      return <ArrowDownRight className="h-5 w-5 text-green-600" />;
    }
    return <ArrowUpRight className="h-5 w-5 text-red-600" />;
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Welcome Header */}
        <div>
          <h1 className="text-3xl font-bold text-gray-900">
            Welcome back, {user.full_name}! 👋
          </h1>
          <p className="text-gray-600 mt-1">
            Here's what's happening with your account today.
          </p>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
          {/* Account Tier */}
          <Card className="hover:shadow-lg transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600">
                Tier {user.kyc_tier}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Badge variant="default" className="mb-2">
                {user.kyc_status}
              </Badge>
            </CardContent>
          </Card>

          {/* Available Balance */}
          <Card className="hover:shadow-lg transition border-l-4 border-l-green-500">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center">
                <Wallet className="h-4 w-4 mr-2" />
                Available Balance
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold text-gray-900">
                {formatCurrency(wallet?.available_balance || 0)}
              </p>
              <p className="text-xs text-gray-500 mt-1">
                Locked: {formatCurrency(wallet?.locked_escrow_funds || 0)}
              </p>
              <Link href="/wallet">
                <button className="mt-3 text-sm text-blue-600 hover:text-blue-700 font-semibold">
                  View Wallet →
                </button>
              </Link>
            </CardContent>
          </Card>

          {/* Total Balance */}
          <Card className="hover:shadow-lg transition border-l-4 border-l-blue-500">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center">
                <TrendingUp className="h-4 w-4 mr-2" />
                Total Balance
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold text-gray-900">
                {formatCurrency(wallet?.total_balance || 0)}
              </p>
              <p className="text-xs text-gray-500 mt-1">
                Including locked funds
              </p>
              <button className="mt-3 text-sm text-blue-600 hover:text-blue-700 font-semibold">
                Deposit Funds →
              </button>
            </CardContent>
          </Card>

          {/* Account Status */}
          <Card className="hover:shadow-lg transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600">
                Account Status
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Badge variant="default" className="mb-2">
                {user.account_status}
              </Badge>
              <p className="text-xs text-gray-500 mt-2">
                User Type: {user.user_type}
              </p>
              <button className="mt-3 text-sm text-blue-600 hover:text-blue-700 font-semibold">
                Upgrade Tier →
              </button>
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
              <Link href="/my-store/products/create">
                <button className="w-full p-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:from-blue-600 hover:to-blue-700 transition flex flex-col items-center space-y-2">
                  <Package className="h-6 w-6" />
                  <span className="text-sm font-semibold">Add Product</span>
                </button>
              </Link>

              <Link href="/my-store">
                <button className="w-full p-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl hover:from-purple-600 hover:to-purple-700 transition flex flex-col items-center space-y-2">
                  <ShoppingBag className="h-6 w-6" />
                  <span className="text-sm font-semibold">My Store</span>
                </button>
              </Link>

              <Link href="/orders/my-orders">
                <button className="w-full p-4 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-700 transition flex flex-col items-center space-y-2">
                  <Package className="h-6 w-6" />
                  <span className="text-sm font-semibold">Orders</span>
                </button>
              </Link>

              <Link href="/marketplace">
                <button className="w-full p-4 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-xl hover:from-orange-600 hover:to-orange-700 transition flex flex-col items-center space-y-2">
                  <ShoppingBag className="h-6 w-6" />
                  <span className="text-sm font-semibold">Analytics</span>
                </button>
              </Link>
            </div>
          </CardContent>
        </Card>

        {/* Recent Activity */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoadingActivity ? (
              <div className="text-center py-12">
                <Spinner size="lg" />
              </div>
            ) : recentActivity.length === 0 ? (
              <div className="text-center py-12">
                <Clock className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                <p className="text-gray-500">No recent activity</p>
                <p className="text-sm text-gray-400 mt-1">
                  Your transactions will appear here
                </p>
              </div>
            ) : (
              <div className="space-y-3">
                {recentActivity.map((activity) => (
                  <div
                    key={activity.id}
                    className="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition"
                  >
                    <div className="flex items-center space-x-4">
                      <div className="h-10 w-10 bg-white rounded-lg flex items-center justify-center">
                        {getActivityIcon(activity.type)}
                      </div>
                      <div>
                        <p className="font-semibold text-gray-900">{activity.type}</p>
                        <p className="text-sm text-gray-600">{activity.description}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className={`font-semibold ${
                        activity.type.includes('CREDIT') || activity.type.includes('DEPOSIT')
                          ? 'text-green-600'
                          : 'text-red-600'
                      }`}>
                        {activity.type.includes('CREDIT') || activity.type.includes('DEPOSIT') ? '+' : '-'}
                        {formatCurrency(activity.amount)}
                      </p>
                      <p className="text-xs text-gray-500">{formatDate(activity.date)}</p>
                    </div>
                  </div>
                ))}
                <Link href="/wallet">
                  <button className="w-full mt-4 py-2 text-blue-600 hover:text-blue-700 font-semibold text-sm">
                    View All Transactions →
                  </button>
                </Link>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
