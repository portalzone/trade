'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Package,
  Clock,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Eye,
  MessageSquare,
  Filter,
  Calendar,
  DollarSign
} from 'lucide-react';
import { formatCurrency, formatDate } from '@/lib/utils';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface Order {
  id: number;
  title: string;
  price: string;
  order_status: string;
  created_at: string;
  buyer_id?: number;
  seller_id: number;
  buyer?: {
    full_name: string;
  };
  seller: {
    full_name: string;
  };
}

export default function MyOrdersPage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const [orders, setOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [mounted, setMounted] = useState(false);
  const [filter, setFilter] = useState<'buying' | 'selling'>('buying');
  const [statusFilter, setStatusFilter] = useState<string>('all');

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    if (mounted && user) {
      fetchOrders();
    }
  }, [mounted, user, filter]);

  const fetchOrders = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      if (!token) {
        router.push('/login');
        return;
      }

      // Use the correct endpoint based on filter
      const endpoint = filter === 'buying' 
        ? 'http://localhost:8000/api/orders/my/buying'
        : 'http://localhost:8000/api/orders/my/selling';

      const response = await fetch(endpoint, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      console.log('Orders API response:', data);
      
      if (data.success) {
        // Handle paginated response
        let allOrders: Order[] = [];
        
        if (data.data?.data && Array.isArray(data.data.data)) {
          allOrders = data.data.data;
        } else if (Array.isArray(data.data)) {
          allOrders = data.data;
        }

        setOrders(allOrders);
        
        if (allOrders.length > 0) {
          toast.success(`Loaded ${allOrders.length} orders`);
        }
      } else {
        setOrders([]);
        toast.error('Failed to load orders');
      }
    } catch (error) {
      console.error('Error fetching orders:', error);
      setOrders([]);
      toast.error('Failed to load orders');
    } finally {
      setIsLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const statusConfig = {
      'PENDING': { variant: 'warning' as const, icon: Clock, label: 'Pending' },
      'ACTIVE': { variant: 'success' as const, icon: CheckCircle, label: 'Active' },
      'IN_ESCROW': { variant: 'default' as const, icon: Package, label: 'In Escrow' },
      'COMPLETED': { variant: 'success' as const, icon: CheckCircle, label: 'Completed' },
      'CANCELLED': { variant: 'destructive' as const, icon: XCircle, label: 'Cancelled' },
      'DISPUTED': { variant: 'destructive' as const, icon: AlertTriangle, label: 'Disputed' },
    };

    const config = statusConfig[status as keyof typeof statusConfig] || statusConfig['PENDING'];
    const Icon = config.icon;

    return (
      <Badge variant={config.variant} className="flex items-center space-x-1">
        <Icon className="h-3 w-3" />
        <span>{config.label}</span>
      </Badge>
    );
  };

  const filteredOrders = Array.isArray(orders) ? orders.filter(order => {
    if (statusFilter === 'all') return true;
    return order.order_status === statusFilter;
  }) : [];

  const stats = {
    total: filteredOrders.length,
    pending: filteredOrders.filter(o => o.order_status === 'PENDING').length,
    completed: filteredOrders.filter(o => o.order_status === 'COMPLETED').length,
    disputed: filteredOrders.filter(o => o.order_status === 'DISPUTED').length,
  };

  if (!mounted) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!user) {
    return null;
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">My Orders</h1>
          <p className="text-gray-600">Track and manage your orders</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Orders</p>
                  <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
                </div>
                <Package className="h-8 w-8 text-blue-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Pending</p>
                  <p className="text-2xl font-bold text-yellow-600">{stats.pending}</p>
                </div>
                <Clock className="h-8 w-8 text-yellow-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Completed</p>
                  <p className="text-2xl font-bold text-green-600">{stats.completed}</p>
                </div>
                <CheckCircle className="h-8 w-8 text-green-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Disputed</p>
                  <p className="text-2xl font-bold text-red-600">{stats.disputed}</p>
                </div>
                <AlertTriangle className="h-8 w-8 text-red-600" />
              </div>
            </CardContent>
          </Card>
        </div>

        <Card className="mb-6">
          <CardContent className="p-4">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
              <div className="flex space-x-2">
                <button
                  onClick={() => setFilter('buying')}
                  className={`px-4 py-2 rounded-lg transition ${
                    filter === 'buying'
                      ? 'bg-blue-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  Buying
                </button>
                {user.user_type === 'SELLER' && (
                  <button
                    onClick={() => setFilter('selling')}
                    className={`px-4 py-2 rounded-lg transition ${
                      filter === 'selling'
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    Selling
                  </button>
                )}
              </div>

              <div className="flex items-center space-x-2">
                <Filter className="h-4 w-4 text-gray-600" />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                >
                  <option value="all">All Status</option>
                  <option value="ACTIVE">Active</option>
                  <option value="IN_ESCROW">In Escrow</option>
                  <option value="COMPLETED">Completed</option>
                  <option value="CANCELLED">Cancelled</option>
                  <option value="DISPUTED">Disputed</option>
                </select>
              </div>
            </div>
          </CardContent>
        </Card>

        {isLoading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : filteredOrders.length === 0 ? (
          <Card>
            <CardContent className="py-20">
              <div className="text-center">
                <Package className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  No orders found
                </h3>
                <p className="text-gray-600 mb-6">
                  {filter === 'buying' ? "You haven't purchased any orders yet" : "You haven't sold any orders yet"}
                </p>
                <Link href="/marketplace">
                  <button className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    Browse Marketplace
                  </button>
                </Link>
              </div>
            </CardContent>
          </Card>
        ) : (
          <div className="space-y-4">
            {filteredOrders.map((order) => (
              <Card key={order.id} className="hover:shadow-lg transition">
                <CardContent className="p-6">
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <div className="flex-1">
                      <div className="flex items-center space-x-3 mb-2">
                        <h3 className="text-lg font-semibold text-gray-900">
                          {order.title}
                        </h3>
                        {getStatusBadge(order.order_status)}
                      </div>
                      
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">
                        <div className="flex items-center space-x-2">
                          <Calendar className="h-4 w-4" />
                          <span>{formatDate(order.created_at)}</span>
                        </div>
                        <div className="flex items-center space-x-2">
                          <DollarSign className="h-4 w-4" />
                          <span className="font-semibold text-gray-900">
                            {formatCurrency(order.price)}
                          </span>
                        </div>
                      </div>

                      {filter === 'buying' && order.seller && (
                        <p className="text-sm text-gray-600 mt-2">
                          Seller: {order.seller.full_name}
                        </p>
                      )}
                      {filter === 'selling' && order.buyer && (
                        <p className="text-sm text-gray-600 mt-2">
                          Buyer: {order.buyer.full_name}
                        </p>
                      )}
                    </div>

                    <div className="flex items-center space-x-2">
                      <Link href={`/orders/${order.id}`}>
                        <button className="px-4 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition flex items-center space-x-2">
                          <Eye className="h-4 w-4" />
                          <span>View</span>
                        </button>
                      </Link>
                      
                      {order.order_status === 'IN_ESCROW' && (
                        <button className="px-4 py-2 border border-red-600 text-red-600 rounded-lg hover:bg-red-50 transition flex items-center space-x-2">
                          <MessageSquare className="h-4 w-4" />
                          <span>Dispute</span>
                        </button>
                      )}
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
