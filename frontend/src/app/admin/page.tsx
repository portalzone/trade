'use client';

import { useEffect, useState } from 'react';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Users, 
  FileCheck, 
  AlertTriangle, 
  TrendingUp,
  Clock,
  CheckCircle,
  XCircle
} from 'lucide-react';
import Link from 'next/link';

interface DashboardStats {
  users: {
    total: number;
    active: number;
    tier1: number;
    tier2: number;
    tier3: number;
  };
  kyc: {
    pending: number;
    approved: number;
    rejected: number;
  };
  disputes: {
    open: number;
    under_review: number;
    resolved: number;
  };
}

export default function AdminDashboard() {
  const { adminToken, isHydrated } = useAdminAuthStore();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Wait for store to hydrate before fetching
    if (isHydrated && adminToken) {
      fetchStats();
    }
  }, [isHydrated, adminToken]);

  const fetchStats = async () => {
    setIsLoading(true);
    try {
      // Fetch KYC stats
      const kycResponse = await fetch('http://localhost:8000/api/admin/business/verifications/statistics', {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });
      const kycData = await kycResponse.json();

      // Fetch dispute stats
      const disputeResponse = await fetch('http://localhost:8000/api/admin/disputes/statistics', {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });
      const disputeData = await disputeResponse.json();

      setStats({
        users: {
          total: 0,
          active: 0,
          tier1: kycData.data?.tier1 || 0,
          tier2: kycData.data?.tier2 || 0,
          tier3: kycData.data?.tier3 || 0,
        },
        kyc: {
          pending: kycData.data?.pending || 0,
          approved: kycData.data?.verified || 0,
          rejected: kycData.data?.rejected || 0,
        },
        disputes: {
          open: disputeData.data?.open || 0,
          under_review: disputeData.data?.under_review || 0,
          resolved: (disputeData.data?.resolved_buyer || 0) + (disputeData.data?.resolved_seller || 0),
        },
      });
    } catch (error) {
      console.error('Error fetching stats:', error);
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p className="text-gray-600 mt-1">Welcome to the T-Trade Admin Portal</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {/* KYC Pending */}
        <Link href="/admin/kyc">
          <Card className="hover:shadow-lg transition cursor-pointer border-l-4 border-l-yellow-500">
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Pending KYC</p>
                  <p className="text-3xl font-bold text-gray-900 mt-2">
                    {stats?.kyc.pending || 0}
                  </p>
                </div>
                <div className="h-12 w-12 bg-yellow-100 rounded-full flex items-center justify-center">
                  <Clock className="h-6 w-6 text-yellow-600" />
                </div>
              </div>
              <p className="text-xs text-gray-500 mt-4">
                Requires review →
              </p>
            </CardContent>
          </Card>
        </Link>

        {/* Open Disputes */}
        <Link href="/admin/disputes">
          <Card className="hover:shadow-lg transition cursor-pointer border-l-4 border-l-red-500">
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Open Disputes</p>
                  <p className="text-3xl font-bold text-gray-900 mt-2">
                    {stats?.disputes.open || 0}
                  </p>
                </div>
                <div className="h-12 w-12 bg-red-100 rounded-full flex items-center justify-center">
                  <AlertTriangle className="h-6 w-6 text-red-600" />
                </div>
              </div>
              <p className="text-xs text-gray-500 mt-4">
                Needs attention →
              </p>
            </CardContent>
          </Card>
        </Link>

        {/* KYC Approved */}
        <Card className="border-l-4 border-l-green-500">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Approved KYC</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">
                  {stats?.kyc.approved || 0}
                </p>
              </div>
              <div className="h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                <CheckCircle className="h-6 w-6 text-green-600" />
              </div>
            </div>
            <p className="text-xs text-gray-500 mt-4">
              Total verified
            </p>
          </CardContent>
        </Card>

        {/* Disputes Resolved */}
        <Card className="border-l-4 border-l-blue-500">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Resolved</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">
                  {stats?.disputes.resolved || 0}
                </p>
              </div>
              <div className="h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                <TrendingUp className="h-6 w-6 text-blue-600" />
              </div>
            </div>
            <p className="text-xs text-gray-500 mt-4">
              Total disputes
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* KYC Overview */}
        <Card>
          <CardHeader>
            <CardTitle>KYC Verification Overview</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <Clock className="h-5 w-5 text-yellow-600" />
                <span className="font-medium text-gray-900">Pending Review</span>
              </div>
              <Badge variant="warning">{stats?.kyc.pending || 0}</Badge>
            </div>

            <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <CheckCircle className="h-5 w-5 text-green-600" />
                <span className="font-medium text-gray-900">Approved</span>
              </div>
              <Badge variant="success">{stats?.kyc.approved || 0}</Badge>
            </div>

            <div className="flex items-center justify-between p-3 bg-red-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <XCircle className="h-5 w-5 text-red-600" />
                <span className="font-medium text-gray-900">Rejected</span>
              </div>
              <Badge variant="destructive">{stats?.kyc.rejected || 0}</Badge>
            </div>

            <Link href="/admin/kyc">
              <button className="w-full mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                Review Pending Applications →
              </button>
            </Link>
          </CardContent>
        </Card>

        {/* Dispute Overview */}
        <Card>
          <CardHeader>
            <CardTitle>Dispute Management</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between p-3 bg-red-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <AlertTriangle className="h-5 w-5 text-red-600" />
                <span className="font-medium text-gray-900">Open Cases</span>
              </div>
              <Badge variant="destructive">{stats?.disputes.open || 0}</Badge>
            </div>

            <div className="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <Clock className="h-5 w-5 text-yellow-600" />
                <span className="font-medium text-gray-900">Under Review</span>
              </div>
              <Badge variant="warning">{stats?.disputes.under_review || 0}</Badge>
            </div>

            <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <CheckCircle className="h-5 w-5 text-green-600" />
                <span className="font-medium text-gray-900">Resolved</span>
              </div>
              <Badge variant="success">{stats?.disputes.resolved || 0}</Badge>
            </div>

            <Link href="/admin/disputes">
              <button className="w-full mt-4 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                Manage Disputes →
              </button>
            </Link>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
