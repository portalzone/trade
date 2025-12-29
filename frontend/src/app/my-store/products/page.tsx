'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Package,
  Plus,
  Edit,
  Trash2,
  Search,
  Filter,
  ArrowLeft,
  TrendingUp,
  Eye,
  AlertTriangle,
  CheckCircle
} from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface Product {
  id: number;
  name: string;
  slug: string;
  price: string;
  stock_quantity: number;
  is_active: boolean;
  views_count: number;
  sales_count: number;
  average_rating: string;
  created_at: string;
}

export default function ManageProductsPage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all');
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
      fetchProducts();
    }
  }, [mounted, user]);

  const fetchProducts = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/products/my', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      console.log('Products data:', data);
      
      if (data.success) {
        // Handle paginated response
        const productsData = data.data?.data || data.data || [];
        setProducts(productsData);
      } else {
        toast.error('Failed to load products');
      }
    } catch (error) {
      console.error('Error fetching products:', error);
      toast.error('Failed to load products');
    } finally {
      setIsLoading(false);
    }
  };

  const handleDeleteProduct = async (productId: number) => {
    if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
      return;
    }

    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`http://localhost:8000/api/products/${productId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      
      if (data.success) {
        toast.success('Product deleted successfully');
        fetchProducts(); // Refresh list
      } else {
        toast.error(data.message || 'Failed to delete product');
      }
    } catch (error) {
      console.error('Error deleting product:', error);
      toast.error('Failed to delete product');
    }
  };

  const filteredProducts = products.filter(product => {
    // Search filter
    if (searchQuery && !product.name.toLowerCase().includes(searchQuery.toLowerCase())) {
      return false;
    }

    // Status filter
    if (statusFilter === 'active' && !product.is_active) return false;
    if (statusFilter === 'inactive' && product.is_active) return false;

    return true;
  });

  const stats = {
    total: products.length,
    active: products.filter(p => p.is_active).length,
    lowStock: products.filter(p => p.stock_quantity < 10).length,
    outOfStock: products.filter(p => p.stock_quantity === 0).length,
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

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <Link href="/my-store">
            <button className="mb-4 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
              <ArrowLeft className="h-5 w-5" />
              <span>Back to Store Dashboard</span>
            </button>
          </Link>

          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 mb-2">Manage Products</h1>
              <p className="text-gray-600">View and manage your product listings</p>
            </div>
            <Link href="/my-store/products/new">
              <button className="flex items-center space-x-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                <Plus className="h-5 w-5" />
                <span>Add Product</span>
              </button>
            </Link>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-600">Total Products</p>
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
                    <p className="text-sm text-gray-600">Active</p>
                    <p className="text-2xl font-bold text-green-600">{stats.active}</p>
                  </div>
                  <CheckCircle className="h-8 w-8 text-green-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-600">Low Stock</p>
                    <p className="text-2xl font-bold text-yellow-600">{stats.lowStock}</p>
                  </div>
                  <AlertTriangle className="h-8 w-8 text-yellow-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-600">Out of Stock</p>
                    <p className="text-2xl font-bold text-red-600">{stats.outOfStock}</p>
                  </div>
                  <Package className="h-8 w-8 text-red-600" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Search and Filters */}
          <Card>
            <CardContent className="p-4">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 gap-4">
                {/* Search */}
                <div className="flex-1 max-w-md">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                      type="text"
                      placeholder="Search products..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                  </div>
                </div>

                {/* Status Filter */}
                <div className="flex items-center space-x-2">
                  <Filter className="h-4 w-4 text-gray-600" />
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value as any)}
                    className="px-4 py-2 border border-gray-300 rounded-lg text-sm"
                  >
                    <option value="all">All Products</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                  </select>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Products List */}
        {filteredProducts.length === 0 ? (
          <Card>
            <CardContent className="py-20">
              <div className="text-center">
                <Package className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  {searchQuery || statusFilter !== 'all' ? 'No products found' : 'No products yet'}
                </h3>
                <p className="text-gray-600 mb-6">
                  {searchQuery || statusFilter !== 'all' 
                    ? 'Try adjusting your filters'
                    : 'Start by adding your first product'}
                </p>
                {!searchQuery && statusFilter === 'all' && (
                  <Link href="/my-store/products/new">
                    <button className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center space-x-2 mx-auto">
                      <Plus className="h-5 w-5" />
                      <span>Add Your First Product</span>
                    </button>
                  </Link>
                )}
              </div>
            </CardContent>
          </Card>
        ) : (
          <div className="space-y-4">
            {filteredProducts.map((product) => (
              <Card key={product.id} className="hover:shadow-lg transition">
                <CardContent className="p-6">
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    {/* Product Info */}
                    <div className="flex items-start space-x-4 flex-1">
                      {/* Placeholder Image */}
                      <div className="h-20 w-20 bg-gradient-to-br from-blue-100 to-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <Package className="h-10 w-10 text-gray-400" />
                      </div>

                      <div className="flex-1 min-w-0">
                        <div className="flex items-center space-x-3 mb-2">
                          <h3 className="text-lg font-semibold text-gray-900 truncate">
                            {product.name}
                          </h3>
                          <Badge variant={product.is_active ? 'success' : 'secondary'}>
                            {product.is_active ? 'Active' : 'Inactive'}
                          </Badge>
                          {product.stock_quantity === 0 && (
                            <Badge variant="destructive">Out of Stock</Badge>
                          )}
                          {product.stock_quantity > 0 && product.stock_quantity < 10 && (
                            <Badge variant="warning">Low Stock</Badge>
                          )}
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                          <div>
                            <p className="text-gray-600">Price</p>
                            <p className="font-semibold text-gray-900">{formatCurrency(product.price)}</p>
                          </div>
                          <div>
                            <p className="text-gray-600">Stock</p>
                            <p className="font-semibold text-gray-900">{product.stock_quantity}</p>
                          </div>
                          <div>
                            <p className="text-gray-600">Views</p>
                            <p className="font-semibold text-gray-900">{product.views_count}</p>
                          </div>
                          <div>
                            <p className="text-gray-600">Sales</p>
                            <p className="font-semibold text-gray-900">{product.sales_count}</p>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center space-x-2">
                      <Link href={`/marketplace/${product.id}`}>
                        <button className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                          <Eye className="h-4 w-4 text-gray-600" />
                        </button>
                      </Link>

                      <Link href={`/my-store/products/${product.id}/edit`}>
                        <button className="p-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition">
                          <Edit className="h-4 w-4" />
                        </button>
                      </Link>

                      <button
                        onClick={() => handleDeleteProduct(product.id)}
                        className="p-2 border border-red-600 text-red-600 rounded-lg hover:bg-red-50 transition"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
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
