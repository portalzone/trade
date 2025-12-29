'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { useCartStore } from '@/store/cartStore';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Package,
  Star,
  ShoppingCart,
  Eye,
  Search,
  Filter
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
  average_rating: string;
  total_reviews: number;
  is_active: boolean;
  seller_id: number;
  storefront: {
    name: string;
    slug: string;
  };
}

export default function MarketplacePage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const addItem = useCartStore((state) => state.addItem);
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
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
      fetchProducts();
    }
  }, [mounted, user]);

  const fetchProducts = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/products', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      
      if (data.success) {
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

  const handleAddToCart = (product: Product, e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    if (product.stock_quantity === 0) {
      toast.error('Product is out of stock');
      return;
    }

    addItem({
      id: product.id,
      name: product.name,
      price: product.price,
      quantity: 1,
      stock_quantity: product.stock_quantity,
      seller_id: product.seller_id,
      seller_name: product.storefront.name,
      slug: product.slug,
    });

    toast.success(`${product.name} added to cart! ��`);
  };

  const filteredProducts = products.filter(product => {
    if (searchQuery && !product.name.toLowerCase().includes(searchQuery.toLowerCase())) {
      return false;
    }
    return true;
  });

  if (!mounted || isLoading) {
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
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Marketplace</h1>
          <p className="text-gray-600">Browse and shop from verified sellers</p>
        </div>

        <Card className="mb-6">
          <CardContent className="p-4">
            <div className="flex flex-col md:flex-row gap-4">
              <div className="flex-1 relative">
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
          </CardContent>
        </Card>

        {filteredProducts.length === 0 ? (
          <Card>
            <CardContent className="py-20">
              <div className="text-center">
                <Package className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  No products found
                </h3>
                <p className="text-gray-600">
                  {searchQuery ? 'Try adjusting your search' : 'Check back later for new products'}
                </p>
              </div>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {filteredProducts.map((product) => (
              <Card key={product.id} className="hover:shadow-xl transition group">
                <CardContent className="p-0">
                  <Link href={`/marketplace/${product.id}`}>
                    <div className="h-48 bg-gradient-to-br from-blue-100 to-purple-100 rounded-t-lg flex items-center justify-center">
                      <Package className="h-20 w-20 text-gray-400 group-hover:scale-110 transition" />
                    </div>
                  </Link>

                  <div className="p-4">
                    <Link href={`/marketplace/${product.id}`}>
                      <h3 className="text-lg font-semibold text-gray-900 mb-2 hover:text-blue-600 transition line-clamp-2">
                        {product.name}
                      </h3>
                    </Link>

                    <div className="flex items-center space-x-2 mb-3">
                      <div className="flex items-center space-x-1">
                        <Star className="h-4 w-4 text-yellow-400 fill-yellow-400" />
                        <span className="text-sm font-semibold text-gray-900">
                          {parseFloat(product.average_rating).toFixed(1)}
                        </span>
                      </div>
                      <span className="text-sm text-gray-600">
                        ({product.total_reviews})
                      </span>
                      {product.stock_quantity === 0 && (
                        <Badge variant="destructive" className="text-xs">Out of Stock</Badge>
                      )}
                    </div>

                    <p className="text-sm text-gray-600 mb-3">
                      by {product.storefront.name}
                    </p>

                    <p className="text-2xl font-bold text-gray-900 mb-4">
                      {formatCurrency(product.price)}
                    </p>

                    <div className="flex items-center space-x-2">
                      <button
                        onClick={(e) => handleAddToCart(product, e)}
                        disabled={product.stock_quantity === 0}
                        className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        <ShoppingCart className="h-4 w-4" />
                        <span className="text-sm">Add to Cart</span>
                      </button>

                      <Link href={`/marketplace/${product.id}`}>
                        <button className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                          <Eye className="h-4 w-4" />
                        </button>
                      </Link>
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
