'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Package,
  Clock,
  CheckCircle,
  XCircle,
  AlertTriangle,
  ArrowLeft,
  User,
  DollarSign,
  Calendar,
  Shield,
  MessageSquare,
  FileText,
  MapPin
} from 'lucide-react';
import { formatCurrency, formatDate } from '@/lib/utils';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface Order {
  id: number;
  title: string;
  description: string;
  price: string;
  currency: string;
  category: string;
  order_status: string;
  created_at: string;
  escrow_locked_at?: string;
  completed_at?: string;
  cancelled_at?: string;
  cancellation_reason?: string;
  buyer_id?: number;
  seller_id: number;
  buyer?: {
    id: number;
    full_name: string;
    email: string;
  };
  seller: {
    id: number;
    full_name: string;
    email: string;
  };
  escrow_lock?: {
    amount: string;
    platform_fee: string;
    lock_type: string;
    locked_at: string;
    released_at?: string;
  };
}

export default function OrderDetailsPage() {
  const params = useParams();
  const router = useRouter();
  const { user } = useAuthStore();
  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    if (mounted && user && params.id) {
      fetchOrder();
    }
  }, [mounted, user, params.id]);

  const fetchOrder = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`http://localhost:8000/api/orders/${params.id}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      console.log('Order details:', data);
      
      if (data.success) {
        setOrder(data.data.order || data.data);
      } else {
        toast.error('Order not found');
        router.push('/orders/my-orders');
      }
    } catch (error) {
      console.error('Error fetching order:', error);
      toast.error('Failed to load order');
      router.push('/orders/my-orders');
    } finally {
      setIsLoading(false);
    }
  };

  const handleCompleteOrder = async () => {
    if (!confirm('Are you sure you want to mark this order as completed? Funds will be released to the seller.')) {
      return;
    }

    setActionLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`http://localhost:8000/api/orders/${params.id}/complete`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      console.log('Complete order response:', data);
      
      if (data.success) {
        toast.success('Order completed! Funds released to seller.');
        fetchOrder();
      } else {
        // Show the actual error message from the API
        const errorMessage = data.error || data.message || 'Failed to complete order';
        toast.error(`Error: ${errorMessage}`);
        console.error('API Error:', data);
      }
    } catch (error) {
      console.error('Error completing order:', error);
      toast.error('Failed to complete order');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDispute = async () => {
    const reason = prompt('Please describe the reason for dispute:');
    if (!reason) return;

    setActionLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`http://localhost:8000/api/orders/${params.id}/dispute`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ reason }),
      });

      const data = await response.json();
      console.log('Dispute response:', data);
      
      if (data.success) {
        toast.success('Dispute raised successfully. Admin will review.');
        fetchOrder();
      } else {
        const errorMessage = data.error || data.message || 'Failed to raise dispute';
        toast.error(`Error: ${errorMessage}`);
        console.error('API Error:', data);
      }
    } catch (error) {
      console.error('Error raising dispute:', error);
      toast.error('Failed to raise dispute');
    } finally {
      setActionLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const statusConfig = {
      'ACTIVE': { variant: 'success' as const, icon: CheckCircle, label: 'Active' },
      'IN_ESCROW': { variant: 'default' as const, icon: Package, label: 'In Escrow' },
      'COMPLETED': { variant: 'success' as const, icon: CheckCircle, label: 'Completed' },
      'CANCELLED': { variant: 'destructive' as const, icon: XCircle, label: 'Cancelled' },
      'DISPUTED': { variant: 'destructive' as const, icon: AlertTriangle, label: 'Disputed' },
    };

    const config = statusConfig[status as keyof typeof statusConfig] || statusConfig['ACTIVE'];
    const Icon = config.icon;

    return (
      <Badge variant={config.variant} className="flex items-center space-x-1">
        <Icon className="h-4 w-4" />
        <span>{config.label}</span>
      </Badge>
    );
  };

  if (!mounted || isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <Package className="h-16 w-16 text-gray-300 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900">Order not found</h2>
          <Link href="/orders/my-orders">
            <button className="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
              Back to Orders
            </button>
          </Link>
        </div>
      </div>
    );
  }

  const isBuyer = user?.id === order.buyer_id;
  const isSeller = user?.id === order.seller_id;
  const canComplete = isBuyer && order.order_status === 'IN_ESCROW';
  const canDispute = isBuyer && order.order_status === 'IN_ESCROW';

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto">
        <Link href="/orders/my-orders">
          <button className="mb-6 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
            <ArrowLeft className="h-5 w-5" />
            <span>Back to Orders</span>
          </button>
        </Link>

        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <h1 className="text-3xl font-bold text-gray-900">Order Details</h1>
            {getStatusBadge(order.order_status)}
          </div>
          <p className="text-gray-600">Order ID: #{order.id}</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <FileText className="h-5 w-5" />
                  <span>Order Information</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <h3 className="text-2xl font-bold text-gray-900 mb-2">
                    {order.title}
                  </h3>
                  <p className="text-gray-700 leading-relaxed">
                    {order.description}
                  </p>
                </div>

                <div className="grid grid-cols-2 gap-4 pt-4 border-t">
                  <div>
                    <p className="text-sm text-gray-600">Category</p>
                    <p className="font-semibold text-gray-900">{order.category}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Currency</p>
                    <p className="font-semibold text-gray-900">{order.currency}</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <DollarSign className="h-5 w-5" />
                  <span>Payment Details</span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-gray-600">Order Amount</span>
                    <span className="text-2xl font-bold text-gray-900">
                      {formatCurrency(order.price)}
                    </span>
                  </div>
                  
                  {order.escrow_lock && (
                    <>
                      <div className="flex justify-between items-center text-sm">
                        <span className="text-gray-600">Platform Fee (2.5%)</span>
                        <span className="text-gray-900">
                          {formatCurrency(order.escrow_lock.platform_fee)}
                        </span>
                      </div>
                      <div className="flex justify-between items-center text-sm pt-3 border-t">
                        <span className="text-gray-600">Seller Receives</span>
                        <span className="font-semibold text-green-600">
                          {formatCurrency(
                            (parseFloat(order.price) - parseFloat(order.escrow_lock.platform_fee)).toString()
                          )}
                        </span>
                      </div>
                    </>
                  )}
                </div>
              </CardContent>
            </Card>

            {order.escrow_lock && (
              <Card className="bg-green-50 border-green-200">
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2 text-green-900">
                    <Shield className="h-5 w-5" />
                    <span>Escrow Protection</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex items-start space-x-3">
                    <CheckCircle className="h-5 w-5 text-green-600 mt-0.5" />
                    <div>
                      <p className="font-semibold text-green-900">Funds Secured</p>
                      <p className="text-sm text-green-700">
                        {formatCurrency(order.escrow_lock.amount)} is locked in escrow
                      </p>
                    </div>
                  </div>
                  
                  <div className="grid grid-cols-2 gap-4 pt-3 border-t border-green-200">
                    <div>
                      <p className="text-sm text-green-700">Locked At</p>
                      <p className="font-medium text-green-900">
                        {formatDate(order.escrow_lock.locked_at)}
                      </p>
                    </div>
                    {order.escrow_lock.released_at && (
                      <div>
                        <p className="text-sm text-green-700">Released At</p>
                        <p className="font-medium text-green-900">
                          {formatDate(order.escrow_lock.released_at)}
                        </p>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            )}

            {order.order_status === 'CANCELLED' && order.cancellation_reason && (
              <Card className="bg-red-50 border-red-200">
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2 text-red-900">
                    <XCircle className="h-5 w-5" />
                    <span>Cancellation Details</span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-red-700">{order.cancellation_reason}</p>
                  {order.cancelled_at && (
                    <p className="text-sm text-red-600 mt-2">
                      Cancelled on {formatDate(order.cancelled_at)}
                    </p>
                  )}
                </CardContent>
              </Card>
            )}
          </div>

          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Clock className="h-5 w-5" />
                  <span>Order Timeline</span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex items-start space-x-3">
                    <div className="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                      <CheckCircle className="h-4 w-4 text-blue-600" />
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">Order Created</p>
                      <p className="text-sm text-gray-600">{formatDate(order.created_at)}</p>
                    </div>
                  </div>

                  {order.escrow_locked_at && (
                    <div className="flex items-start space-x-3">
                      <div className="h-8 w-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <Shield className="h-4 w-4 text-green-600" />
                      </div>
                      <div>
                        <p className="font-semibold text-gray-900">Funds Locked</p>
                        <p className="text-sm text-gray-600">{formatDate(order.escrow_locked_at)}</p>
                      </div>
                    </div>
                  )}

                  {order.completed_at && (
                    <div className="flex items-start space-x-3">
                      <div className="h-8 w-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <CheckCircle className="h-4 w-4 text-purple-600" />
                      </div>
                      <div>
                        <p className="font-semibold text-gray-900">Order Completed</p>
                        <p className="text-sm text-gray-600">{formatDate(order.completed_at)}</p>
                      </div>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <User className="h-5 w-5" />
                  <span>Seller Information</span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <div>
                    <p className="text-sm text-gray-600">Name</p>
                    <p className="font-semibold text-gray-900">{order.seller.full_name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Email</p>
                    <p className="text-gray-900">{order.seller.email}</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {order.buyer && (
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <User className="h-5 w-5" />
                    <span>Buyer Information</span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    <div>
                      <p className="text-sm text-gray-600">Name</p>
                      <p className="font-semibold text-gray-900">{order.buyer.full_name}</p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Email</p>
                      <p className="text-gray-900">{order.buyer.email}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}

            {(canComplete || canDispute) && (
              <Card>
                <CardHeader>
                  <CardTitle>Actions</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {canComplete && (
                    <button
                      onClick={handleCompleteOrder}
                      disabled={actionLoading}
                      className="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                    >
                      <CheckCircle className="h-5 w-5" />
                      <span>Complete Order & Release Funds</span>
                    </button>
                  )}

                  {canDispute && (
                    <button
                      onClick={handleDispute}
                      disabled={actionLoading}
                      className="w-full px-4 py-3 border-2 border-red-600 text-red-600 rounded-lg hover:bg-red-50 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                    >
                      <MessageSquare className="h-5 w-5" />
                      <span>Raise Dispute</span>
                    </button>
                  )}
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
