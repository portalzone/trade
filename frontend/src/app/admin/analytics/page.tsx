'use client';

import { useEffect, useState } from 'react';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { 
  TrendingUp, 
  Users, 
  DollarSign, 
  ShoppingCart,
  AlertCircle,
  CheckCircle,
  Calendar
} from 'lucide-react';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

export default function AnalyticsPage() {
  const { adminToken, isHydrated } = useAdminAuthStore();
  const [isLoading, setIsLoading] = useState(true);
  const [timeRange, setTimeRange] = useState('30');
  
  const [overview, setOverview] = useState<any>(null);
  const [userGrowth, setUserGrowth] = useState<any[]>([]);
  const [transactionVolume, setTransactionVolume] = useState<any[]>([]);
  const [kycRates, setKycRates] = useState<any>(null);
  const [userDistribution, setUserDistribution] = useState<any>(null);

  useEffect(() => {
    if (isHydrated && adminToken) {
      fetchAnalytics();
    }
  }, [isHydrated, adminToken, timeRange]);

  const fetchAnalytics = async () => {
    setIsLoading(true);
    try {
      // Fetch all analytics data
      const [overviewRes, growthRes, volumeRes, kycRes, distRes] = await Promise.all([
        fetch(`http://localhost:8000/api/admin/analytics/overview?range=${timeRange}`, {
          headers: { 'Authorization': `Bearer ${adminToken}` },
        }),
        fetch(`http://localhost:8000/api/admin/analytics/user-growth?days=${timeRange}`, {
          headers: { 'Authorization': `Bearer ${adminToken}` },
        }),
        fetch(`http://localhost:8000/api/admin/analytics/transaction-volume?days=${timeRange}`, {
          headers: { 'Authorization': `Bearer ${adminToken}` },
        }),
        fetch('http://localhost:8000/api/admin/analytics/kyc-rates', {
          headers: { 'Authorization': `Bearer ${adminToken}` },
        }),
        fetch('http://localhost:8000/api/admin/analytics/user-distribution', {
          headers: { 'Authorization': `Bearer ${adminToken}` },
        }),
      ]);

      const [overviewData, growthData, volumeData, kycData, distData] = await Promise.all([
        overviewRes.json(),
        growthRes.json(),
        volumeRes.json(),
        kycRes.json(),
        distRes.json(),
      ]);

      if (overviewData.success) setOverview(overviewData.data);
      if (growthData.success) setUserGrowth(growthData.data);
      if (volumeData.success) setTransactionVolume(volumeData.data);
      if (kycData.success) setKycRates(kycData.data);
      if (distData.success) setUserDistribution(distData.data);
    } catch (error) {
      console.error('Failed to fetch analytics:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-NG', {
      style: 'currency',
      currency: 'NGN',
      minimumFractionDigits: 0,
    }).format(amount);
  };

  if (!isHydrated || isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Analytics Dashboard</h1>
          <p className="text-gray-600 mt-1">Platform performance and insights</p>
        </div>

        {/* Time Range Selector */}
        <select
          value={timeRange}
          onChange={(e) => setTimeRange(e.target.value)}
          className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
        >
          <option value="7">Last 7 days</option>
          <option value="30">Last 30 days</option>
          <option value="90">Last 90 days</option>
          <option value="365">Last year</option>
        </select>
      </div>

      {/* Overview Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Users</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">{overview?.users?.total || 0}</p>
                <p className="text-sm text-green-600 mt-1">+{overview?.users?.new_this_month || 0} this month</p>
              </div>
              <Users className="h-12 w-12 text-blue-600 opacity-50" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Volume</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">
                  {formatCurrency(overview?.transactions?.total_volume || 0)}
                </p>
                <p className="text-sm text-green-600 mt-1">
                  {formatCurrency(overview?.transactions?.this_month || 0)} this month
                </p>
              </div>
              <DollarSign className="h-12 w-12 text-green-600 opacity-50" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Active Orders</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">{overview?.orders?.active || 0}</p>
                <p className="text-sm text-gray-600 mt-1">{overview?.orders?.total || 0} total</p>
              </div>
              <ShoppingCart className="h-12 w-12 text-purple-600 opacity-50" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Pending Disputes</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">{overview?.disputes?.pending || 0}</p>
                <p className="text-sm text-gray-600 mt-1">{overview?.disputes?.resolved || 0} resolved</p>
              </div>
              <AlertCircle className="h-12 w-12 text-red-600 opacity-50" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Charts Row 1 */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* User Growth Chart */}
        <Card>
          <CardHeader>
            <CardTitle>User Growth</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={userGrowth}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="date" tick={{ fontSize: 12 }} />
                <YAxis />
                <Tooltip />
                <Legend />
                <Line type="monotone" dataKey="users" stroke="#3b82f6" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Transaction Volume Chart */}
        <Card>
          <CardHeader>
            <CardTitle>Transaction Volume</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={transactionVolume}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="date" tick={{ fontSize: 12 }} />
                <YAxis />
                <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                <Legend />
                <Bar dataKey="volume" fill="#10b981" />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      {/* Charts Row 2 */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* User Distribution by Type */}
        <Card>
          <CardHeader>
            <CardTitle>User Distribution by Type</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={userDistribution?.by_type || []}
                  dataKey="count"
                  nameKey="user_type"
                  cx="50%"
                  cy="50%"
                  outerRadius={100}
                  label
                >
                  {(userDistribution?.by_type || []).map((entry: any, index: number) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* KYC Approval Rates */}
        <Card>
          <CardHeader>
            <CardTitle>KYC Approval Rates</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Tier 2 */}
            <div>
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Tier 2 (Business)</span>
                <span className="text-sm font-bold text-gray-900">{kycRates?.tier2?.approval_rate || 0}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-3">
                <div
                  className="bg-blue-600 h-3 rounded-full"
                  style={{ width: `${kycRates?.tier2?.approval_rate || 0}%` }}
                ></div>
              </div>
              <div className="flex justify-between mt-2 text-xs text-gray-600">
                <span>✅ {kycRates?.tier2?.approved || 0} approved</span>
                <span>❌ {kycRates?.tier2?.rejected || 0} rejected</span>
                <span>⏳ {kycRates?.tier2?.pending || 0} pending</span>
              </div>
            </div>

            {/* Tier 3 */}
            <div>
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Tier 3 (Enterprise)</span>
                <span className="text-sm font-bold text-gray-900">{kycRates?.tier3?.approval_rate || 0}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-3">
                <div
                  className="bg-purple-600 h-3 rounded-full"
                  style={{ width: `${kycRates?.tier3?.approval_rate || 0}%` }}
                ></div>
              </div>
              <div className="flex justify-between mt-2 text-xs text-gray-600">
                <span>✅ {kycRates?.tier3?.approved || 0} approved</span>
                <span>❌ {kycRates?.tier3?.rejected || 0} rejected</span>
                <span>⏳ {kycRates?.tier3?.pending || 0} pending</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* User Tier Distribution */}
      <Card>
        <CardHeader>
          <CardTitle>User Distribution by KYC Tier</CardTitle>
        </CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={userDistribution?.by_tier || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="kyc_tier" tickFormatter={(value) => `Tier ${value}`} />
              <YAxis />
              <Tooltip labelFormatter={(value) => `Tier ${value}`} />
              <Legend />
              <Bar dataKey="count" fill="#8b5cf6" />
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>
    </div>
  );
}
