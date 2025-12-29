'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Store,
  Package,
  DollarSign,
  TrendingUp,
  Eye,
  Star,
  Plus,
  Settings,
  ShoppingBag,
  Users,
  BarChart3
} from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface Storefront {
  id: number;
  name: string;
  slug: string;
  description: string;
  status: string;
  total_products: number;
  total_sales: number;
  total_revenue: string;
  average_rating: string;
  total_reviews: number;
  is_verified: boolean;
}

export default function MyStorePage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const [storefront, setStorefront] = useState<Storefront | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    
    if (mounted && user) {
      if (user.user_type !== 'SELLER') {
        toast.error('Only sellers can access this page');
        router.push('/dashboard');
        return;
      }
      fetchStorefront();
    }
  }, [mounted, user]);

  const fetchStorefront = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      // Fixed: Use /api/storefront/my (singular, not plural)
      const response = await fetch('http://localhost:8000/api/storefront/my', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      console.log('Storefront data:', data);
      
      if (data.success && data.data) {
        setStorefront(data.data);
      } else {
        setStorefront(null);
      }
    } catch (error) {
      console.error('Error fetching storefront:', error);
    } finally {
      setIsLoading(false);
    }
  };

  if (!mounted || isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!user || user.user_type !== 'SELLER') {
    return null;
  }

  if (!storefront) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
        <div className="max-w-4xl mx-auto">
          <Card className="border-2 border-dashed">
            <CardContent className="py-20">
              <div className="text-center">
                <Store className="h-20 w-20 text-gray-300 mx-auto mb-6" />
                <h2 className="text-3xl font-bold text-gray-900 mb-4">
                  Create Your Storefront
                </h2>
                <p className="text-gray-600 mb-8 max-w-2xl mx-auto">
                  Start selling on T-Trade! Create your storefront to showcase your products,
                  manage inventory, and grow your business.
                </p>
                <Link href="/my-store/create">
                  <button className="px-8 py-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold text-lg flex items-center space-x-2 mx-auto">
                    <Plus className="h-5 w-5" />
                    <span>Create Storefront</span>
                  </button>
                </Link>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 mb-2">{storefront.name}</h1>
              <p className="text-gray-600">{storefront.description}</p>
            </div>
            <div className="flex items-center space-x-3">
              <Badge variant={storefront.status === 'active' ? 'success' : 'secondary'}>
                {storefront.status}
              </Badge>
              {storefront.is_verified && (
                <Badge variant="default" className="bg-blue-600">
                  ✓ Verified
                </Badge>
              )}
            </div>
          </div>
          
          <div className="flex items-center space-x-4">
            <Link href={`/store/${storefront.slug}`}>
              <button className="flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <Eye className="h-4 w-4" />
                <span>View Store</span>
              </button>
            </Link>
            <Link href="/my-store/settings">
              <button className="flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <Settings className="h-4 w-4" />
                <span>Store Settings</span>
              </button>
            </Link>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Products</p>
                  <p className="text-3xl font-bold text-gray-900">{storefront.total_products}</p>
                </div>
                <Package className="h-10 w-10 text-blue-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Sales</p>
                  <p className="text-3xl font-bold text-gray-900">{storefront.total_sales}</p>
                </div>
                <ShoppingBag className="h-10 w-10 text-green-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Revenue</p>
                  <p className="text-2xl font-bold text-gray-900">
                    {formatCurrency(storefront.total_revenue)}
                  </p>
                </div>
                <DollarSign className="h-10 w-10 text-purple-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Rating</p>
                  <div className="flex items-center space-x-2">
                    <p className="text-3xl font-bold text-gray-900">
                      {parseFloat(storefront.average_rating).toFixed(1)}
                    </p>
                    <Star className="h-6 w-6 text-yellow-400 fill-yellow-400" />
                  </div>
                  <p className="text-xs text-gray-500">{storefront.total_reviews} reviews</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <Link href="/my-store/products/new">
            <Card className="hover:shadow-lg transition cursor-pointer border-2 border-blue-200 bg-blue-50">
              <CardContent className="pt-6 pb-6">
                <div className="flex items-center space-x-4">
                  <div className="h-12 w-12 bg-blue-600 rounded-lg flex items-center justify-center">
                    <Plus className="h-6 w-6 text-white" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-gray-900">Add Product</h3>
                    <p className="text-sm text-gray-600">Create a new product listing</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </Link>

          <Link href="/my-store/products">
            <Card className="hover:shadow-lg transition cursor-pointer">
              <CardContent className="pt-6 pb-6">
                <div className="flex items-center space-x-4">
                  <div className="h-12 w-12 bg-green-600 rounded-lg flex items-center justify-center">
                    <Package className="h-6 w-6 text-white" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-gray-900">Manage Products</h3>
                    <p className="text-sm text-gray-600">View and edit your products</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </Link>

          <Link href="/orders/my-orders">
            <Card className="hover:shadow-lg transition cursor-pointer">
              <CardContent className="pt-6 pb-6">
                <div className="flex items-center space-x-4">
                  <div className="h-12 w-12 bg-purple-600 rounded-lg flex items-center justify-center">
                    <TrendingUp className="h-6 w-6 text-white" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-gray-900">View Orders</h3>
                    <p className="text-sm text-gray-600">Manage your sales</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </Link>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <BarChart3 className="h-5 w-5" />
              <span>Store Overview</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <Package className="h-5 w-5 text-blue-600" />
                  <span className="text-gray-700">Active Products</span>
                </div>
                <span className="font-semibold text-gray-900">{storefront.total_products}</span>
              </div>

              <div className="flex items-center justify-between py-3 border-b">
                <div className="flex items-center space-x-3">
                  <Users className="h-5 w-5 text-green-600" />
                  <span className="text-gray-700">Total Reviews</span>
                </div>
                <span className="font-semibold text-gray-900">{storefront.total_reviews}</span>
              </div>

              <div className="flex items-center justify-between py-3">
                <div className="flex items-center space-x-3">
                  <DollarSign className="h-5 w-5 text-purple-600" />
                  <span className="text-gray-700">Lifetime Revenue</span>
                </div>
                <span className="font-semibold text-gray-900">
                  {formatCurrency(storefront.total_revenue)}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
