'use client';

import { useEffect, useState } from 'react';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Users, 
  Search,
  Filter,
  Eye,
  Ban,
  CheckCircle,
  XCircle,
  Shield,
  Mail,
  Phone,
  Calendar,
  Edit
} from 'lucide-react';
import toast from 'react-hot-toast';

interface User {
  id: number;
  email: string;
  phone_number: string;
  full_name: string;
  username: string;
  user_type: string;
  kyc_status: string;
  kyc_tier: number;
  account_status: string;
  email_verified_at: string | null;
  phone_verified_at: string | null;
  mfa_enabled: boolean;
  created_at: string;
  wallet?: {
    available_balance: number;
    locked_escrow_funds: number;
    total_balance: number;
  };
}

interface Statistics {
  total_users: number;
  active_users: number;
  suspended_users: number;
  banned_users: number;
  by_type: {
    buyers: number;
    sellers: number;
    admins: number;
  };
  by_tier: {
    tier1: number;
    tier2: number;
    tier3: number;
  };
  verified_email: number;
  verified_phone: number;
  mfa_enabled: number;
}

export default function AdminUsersPage() {
  const { adminToken, isHydrated } = useAdminAuthStore();
  const [users, setUsers] = useState<User[]>([]);
  const [stats, setStats] = useState<Statistics | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [modalType, setModalType] = useState<'status' | 'email'>('status');
  
  // Status update fields
  const [newStatus, setNewStatus] = useState('');
  const [statusReason, setStatusReason] = useState('');
  
  // Email update fields
  const [newEmail, setNewEmail] = useState('');
  const [emailReason, setEmailReason] = useState('');
  
  const [isUpdating, setIsUpdating] = useState(false);
  
  // Filters
  const [search, setSearch] = useState('');
  const [filterType, setFilterType] = useState('');
  const [filterTier, setFilterTier] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  useEffect(() => {
    if (isHydrated && adminToken) {
      fetchStats();
      fetchUsers();
    }
  }, [isHydrated, adminToken, search, filterType, filterTier, filterStatus, currentPage]);

  const fetchStats = async () => {
    try {
      const response = await fetch('http://localhost:8000/api/admin/users/statistics', {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });
      const data = await response.json();
      if (data.success) {
        setStats(data.data);
      }
    } catch (error) {
      console.error('Failed to fetch stats:', error);
    }
  };

  const fetchUsers = async () => {
    setIsLoading(true);
    try {
      const params = new URLSearchParams();
      if (search) params.append('search', search);
      if (filterType) params.append('user_type', filterType);
      if (filterTier) params.append('kyc_tier', filterTier);
      if (filterStatus) params.append('account_status', filterStatus);
      params.append('page', currentPage.toString());

      const response = await fetch(`http://localhost:8000/api/admin/users?${params}`, {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });
      const data = await response.json();
      if (data.success) {
        setUsers(data.data.data);
        setTotalPages(data.data.last_page);
      }
    } catch (error) {
      console.error('Failed to fetch users:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleUpdateStatus = async () => {
    if (!selectedUser || !newStatus || !statusReason.trim()) {
      toast.error('Please select status and provide reason');
      return;
    }

    setIsUpdating(true);
    try {
      const response = await fetch(`http://localhost:8000/api/admin/users/${selectedUser.id}/status`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          account_status: newStatus,
          reason: statusReason
        }),
      });
      const data = await response.json();
      if (data.success) {
        toast.success('User status updated successfully!');
        closeModal();
        fetchUsers();
        fetchStats();
      } else {
        toast.error(data.message || 'Failed to update status');
      }
    } catch (error) {
      toast.error('Connection error');
    } finally {
      setIsUpdating(false);
    }
  };

  const handleUpdateEmail = async () => {
    if (!selectedUser || !newEmail.trim() || !emailReason.trim()) {
      toast.error('Please provide email and reason');
      return;
    }

    setIsUpdating(true);
    try {
      const response = await fetch(`http://localhost:8000/api/admin/users/${selectedUser.id}/email`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          email: newEmail,
          reason: emailReason
        }),
      });
      const data = await response.json();
      if (data.success) {
        toast.success('User email updated successfully!');
        closeModal();
        fetchUsers();
      } else {
        toast.error(data.message || 'Failed to update email');
      }
    } catch (error) {
      toast.error('Connection error');
    } finally {
      setIsUpdating(false);
    }
  };

  const openModal = (user: User, type: 'status' | 'email') => {
    setSelectedUser(user);
    setModalType(type);
    if (type === 'email') {
      setNewEmail(user.email);
    }
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setSelectedUser(null);
    setNewStatus('');
    setStatusReason('');
    setNewEmail('');
    setEmailReason('');
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'ACTIVE': return 'bg-green-100 text-green-800';
      case 'SUSPENDED': return 'bg-yellow-100 text-yellow-800';
      case 'BANNED': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getTierBadge = (tier: number) => {
    const colors = ['bg-gray-100 text-gray-800', 'bg-blue-100 text-blue-800', 'bg-purple-100 text-purple-800'];
    return colors[tier - 1] || colors[0];
  };

  if (isLoading && users.length === 0) {
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
        <h1 className="text-3xl font-bold text-gray-900">User Management</h1>
        <p className="text-gray-600 mt-1">Manage and monitor all platform users</p>
      </div>

      {/* Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Users</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">{stats?.total_users || 0}</p>
              </div>
              <Users className="h-12 w-12 text-blue-600 opacity-50" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Active</p>
                <p className="text-3xl font-bold text-green-600 mt-2">{stats?.active_users || 0}</p>
              </div>
              <CheckCircle className="h-12 w-12 text-green-600 opacity-50" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Suspended</p>
                <p className="text-3xl font-bold text-yellow-600 mt-2">{stats?.suspended_users || 0}</p>
              </div>
              <XCircle className="h-12 w-12 text-yellow-600 opacity-50" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Banned</p>
                <p className="text-3xl font-bold text-red-600 mt-2">{stats?.banned_users || 0}</p>
              </div>
              <Ban className="h-12 w-12 text-red-600 opacity-50" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
              <input
                type="text"
                placeholder="Search users..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Types</option>
              <option value="BUYER">Buyers</option>
              <option value="SELLER">Sellers</option>
              <option value="ADMIN">Admins</option>
            </select>

            <select
              value={filterTier}
              onChange={(e) => setFilterTier(e.target.value)}
              className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Tiers</option>
              <option value="1">Tier 1</option>
              <option value="2">Tier 2</option>
              <option value="3">Tier 3</option>
            </select>

            <select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Status</option>
              <option value="ACTIVE">Active</option>
              <option value="SUSPENDED">Suspended</option>
              <option value="BANNED">Banned</option>
            </select>
          </div>
        </CardContent>
      </Card>

      {/* Users Table */}
      <Card>
        <CardHeader>
          <CardTitle>Users ({users.length})</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">KYC Tier</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {users.map((user) => (
                  <tr key={user.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4">
                      <div>
                        <p className="font-semibold text-gray-900">{user.full_name}</p>
                        <p className="text-sm text-gray-500">{user.email}</p>
                        <p className="text-sm text-gray-500">@{user.username}</p>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <Badge variant={user.user_type === 'ADMIN' ? 'destructive' : 'default'}>
                        {user.user_type}
                      </Badge>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`px-3 py-1 rounded-full text-sm font-semibold ${getTierBadge(user.kyc_tier)}`}>
                        Tier {user.kyc_tier}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`px-3 py-1 rounded-full text-sm font-semibold ${getStatusColor(user.account_status)}`}>
                        {user.account_status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500">
                      {new Date(user.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex gap-2">
                        <button
                          onClick={() => openModal(user, 'status')}
                          className="text-blue-600 hover:text-blue-700 font-semibold text-sm"
                          title="Update Status"
                        >
                          Status
                        </button>
                        <button
                          onClick={() => openModal(user, 'email')}
                          className="text-green-600 hover:text-green-700 font-semibold text-sm flex items-center gap-1"
                          title="Update Email"
                        >
                          <Mail className="h-4 w-4" />
                          Email
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div className="mt-6 flex items-center justify-between">
            <button
              onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
              disabled={currentPage === 1}
              className="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 disabled:opacity-50"
            >
              Previous
            </button>
            <span className="text-gray-600">
              Page {currentPage} of {totalPages}
            </span>
            <button
              onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
              disabled={currentPage === totalPages}
              className="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </CardContent>
      </Card>

      {/* Modal */}
      {showModal && selectedUser && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
            <h2 className="text-2xl font-bold mb-6">
              {modalType === 'status' ? 'Update User Status' : 'Update User Email'}
            </h2>

            <div className="mb-6">
              <p className="mb-2"><strong>User:</strong> {selectedUser.full_name}</p>
              <p className="mb-2"><strong>Current Email:</strong> {selectedUser.email}</p>
              {modalType === 'status' && (
                <p className="mb-4">
                  <strong>Current Status:</strong>{' '}
                  <span className={`px-2 py-1 rounded text-sm ${getStatusColor(selectedUser.account_status)}`}>
                    {selectedUser.account_status}
                  </span>
                </p>
              )}
            </div>

            {modalType === 'status' ? (
              <>
                <div className="mb-4">
                  <label className="block text-sm font-medium mb-2">New Status</label>
                  <select
                    value={newStatus}
                    onChange={(e) => setNewStatus(e.target.value)}
                    className="w-full px-4 py-2 border rounded-lg"
                  >
                    <option value="">Select status...</option>
                    <option value="ACTIVE">Active</option>
                    <option value="SUSPENDED">Suspended</option>
                    <option value="BANNED">Banned</option>
                  </select>
                </div>

                <div className="mb-6">
                  <label className="block text-sm font-medium mb-2">Reason (Required)</label>
                  <textarea
                    value={statusReason}
                    onChange={(e) => setStatusReason(e.target.value)}
                    rows={3}
                    className="w-full px-4 py-2 border rounded-lg"
                    placeholder="Explain why you're changing the status..."
                  />
                </div>
              </>
            ) : (
              <>
                <div className="mb-4">
                  <label className="block text-sm font-medium mb-2">New Email</label>
                  <input
                    type="email"
                    value={newEmail}
                    onChange={(e) => setNewEmail(e.target.value)}
                    className="w-full px-4 py-2 border rounded-lg"
                    placeholder="user@example.com"
                  />
                </div>

                <div className="mb-6">
                  <label className="block text-sm font-medium mb-2">Reason (Required)</label>
                  <textarea
                    value={emailReason}
                    onChange={(e) => setEmailReason(e.target.value)}
                    rows={3}
                    className="w-full px-4 py-2 border rounded-lg"
                    placeholder="Explain why you're changing the email..."
                  />
                </div>
              </>
            )}

            <div className="flex gap-4">
              <button
                onClick={closeModal}
                className="flex-1 bg-gray-200 text-gray-800 py-2 rounded-lg hover:bg-gray-300"
              >
                Cancel
              </button>
              <button
                onClick={modalType === 'status' ? handleUpdateStatus : handleUpdateEmail}
                disabled={
                  isUpdating ||
                  (modalType === 'status' && (!newStatus || !statusReason.trim())) ||
                  (modalType === 'email' && (!newEmail.trim() || !emailReason.trim()))
                }
                className="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {isUpdating ? 'Updating...' : 'Update'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
