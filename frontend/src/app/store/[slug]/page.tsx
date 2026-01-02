'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { useCartStore } from '@/store/cartStore';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Store,
  Package,
  Star,
  ShoppingCart,
  Mail,
  Phone,
  MapPin
} from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface Product {
  id: number;
  name: string;
  slug: string;
  description: string;
  price: string;
  stock_quantity: number;
  average_rating: string;
  total_reviews: number;
  is_active: boolean;
}

interface StorefrontData {
  id: number;
  name: string;
  slug: string;
  description: string;
  contact_email: string;
  contact_phone: string;
  is_active: boolean;
  products: Product[];
}

export default function PublicStorefrontPage() {
  const params = useParams();
  const slug = params.slug as string;
  const { user } = useAuthStore();
  const addItem = useCartStore((state) => state.addItem);
  
  const [store, setStore] = useState<StorefrontData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted) {
      fetchStorefront();
    }
  }, [mounted, slug]);

  const fetchStorefront = async () => {
    setIsLoading(true);
    try {
      const response = await fetch(`http://localhost:8000/api/store/${slug}`);
      const data = await response.json();

      if (data.success) {
        setStore(data.data);
      } else {
        toast.error('Store not found');
      }
    } catch (error) {
      console.error('Error fetching storefront:', error);
      toast.error('Failed to load store');
    } finally {
      setIsLoading(false);
    }
  };

  const handleAddToCart = (product: Product, e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    if (!user) {
      toast.error('Please login to add items to cart');
      return;
    }

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
      seller_id: store!.id,
      seller_name: store!.name,
      slug: product.slug,
    });

    toast.success(`${product.name} added to cart! 🛒`);
  };

  if (!mounted || isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!store) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
        <div className="max-w-4xl mx-auto">
          <Card>
            <CardContent className="py-20">
              <div className="text-center">
                <Store className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  Store not found
                </h3>
                <p className="text-gray-600 mb-6">
                  The store you're looking for doesn't exist
                </p>
                <Link href="/marketplace">
                  <button className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    Browse Marketplace
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
        {/* Store Header */}
        <Card className="mb-8">
          <CardContent className="p-8">
            <div className="flex items-start space-x-6">
              <div className="h-24 w-24 bg-gradient-to-br from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center flex-shrink-0">
                <Store className="h-12 w-12 text-white" />
              </div>

              <div className="flex-1">
                <h1 className="text-3xl font-bold text-gray-900 mb-2">{store.name}</h1>
                {store.description && (
                  <p className="text-gray-600 mb-4">{store.description}</p>
                )}

                <div className="flex flex-wrap gap-4 text-sm text-gray-600">
                  {store.contact_email && (
                    <div className="flex items-center space-x-2">
                      <Mail className="h-4 w-4" />
                      <span>{store.contact_email}</span>
                    </div>
                  )}
                  {store.contact_phone && (
                    <div className="flex items-center space-x-2">
                      <Phone className="h-4 w-4" />
                      <span>{store.contact_phone}</span>
                    </div>
                  )}
                </div>
              </div>

              <Badge variant={store.is_active ? "default" : "destructive"} className="text-sm">
                {store.is_active ? 'Active' : 'Inactive'}
              </Badge>
            </div>
          </CardContent>
        </Card>

        {/* Products */}
        <div className="mb-6">
          <h2 className="text-2xl font-bold text-gray-900 mb-4">
            Products ({store.products?.length || 0})
          </h2>
        </div>

        {!store.products || store.products.length === 0 ? (
          <Card>
            <CardContent className="py-20">
              <div className="text-center">
                <Package className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  No products yet
                </h3>
                <p className="text-gray-600">
                  This store hasn't listed any products yet
                </p>
              </div>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {store.products.map((product) => {
              const isOutOfStock = product.stock_quantity === 0;

              return (
                <Card key={product.id} className="hover:shadow-xl transition group">
                  <CardContent className="p-0">
                    <Link href={`/marketplace/${product.id}`}>
                      <div className="h-48 bg-gradient-to-br from-blue-100 to-purple-100 rounded-t-lg flex items-center justify-center relative">
                        <Package className="h-20 w-20 text-gray-400 group-hover:scale-110 transition" />
                        {isOutOfStock && (
                          <div className="absolute inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center rounded-t-lg">
                            <span className="text-white font-bold text-lg">OUT OF STOCK</span>
                          </div>
                        )}
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
                        {isOutOfStock && (
                          <Badge variant="destructive" className="text-xs">Out of Stock</Badge>
                        )}
                      </div>

                      <p className="text-2xl font-bold text-gray-900 mb-4">
                        {formatCurrency(product.price)}
                      </p>

                      <button
                        onClick={(e) => handleAddToCart(product, e)}
                        disabled={isOutOfStock || !user}
                        className="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400"
                      >
                        <ShoppingCart className="h-4 w-4" />
                        <span className="text-sm">
                          {isOutOfStock ? 'Out of Stock' : !user ? 'Login to Buy' : 'Add to Cart'}
                        </span>
                      </button>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
