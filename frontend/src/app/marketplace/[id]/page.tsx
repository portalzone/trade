'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { useCartStore } from '@/store/cartStore';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Package,
  Star,
  Store,
  ShoppingCart,
  ArrowLeft,
  Check,
  Shield
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
  is_active: boolean;
  average_rating: string;
  total_reviews: number;
  seller_id: number;
  storefront: {
    id: number;
    name: string;
    slug: string;
    is_verified: boolean;
  };
}

export default function ProductDetailsPage() {
  const params = useParams();
  const router = useRouter();
  const { user } = useAuthStore();
  const addItem = useCartStore((state) => state.addItem);
  const cartItems = useCartStore((state) => state.items);
  const [product, setProduct] = useState<Product | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [quantity, setQuantity] = useState(1);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    if (mounted && params.id) {
      fetchProduct();
    }
  }, [mounted, params.id, user]);

  const fetchProduct = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`http://localhost:8000/api/products/${params.id}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      
      if (data.success && data.data) {
        setProduct(data.data);
      } else {
        toast.error('Product not found');
        router.push('/marketplace');
      }
    } catch (error) {
      console.error('Error fetching product:', error);
      toast.error('Failed to load product');
      router.push('/marketplace');
    } finally {
      setIsLoading(false);
    }
  };

  const handleAddToCart = () => {
    if (!product) {
      console.error('No product data!');
      return;
    }

    const cartItem = {
      id: product.id,
      name: product.name,
      price: product.price,
      quantity: quantity,
      stock_quantity: product.stock_quantity,
      seller_id: product.seller_id,
      seller_name: product.storefront.name,
      slug: product.slug,
    };

    console.log('Adding to cart:', cartItem);
    console.log('Current cart before add:', cartItems);

    addItem(cartItem);

    // Check cart after adding
    setTimeout(() => {
      const updatedCart = useCartStore.getState().items;
      console.log('Cart after add:', updatedCart);
      console.log('LocalStorage after add:', localStorage.getItem('cart-storage'));
    }, 100);

    toast.success(`${quantity} ${product.name} added to cart! 🛒`);
    setQuantity(1);
  };

  if (!mounted || isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!product) {
    return null;
  }

  const isOutOfStock = product.stock_quantity === 0;
  const canAddToCart = !isOutOfStock && quantity <= product.stock_quantity;

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto">
        <Link href="/marketplace">
          <button className="mb-6 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
            <ArrowLeft className="h-5 w-5" />
            <span>Back to Marketplace</span>
          </button>
        </Link>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div className="h-96 bg-gradient-to-br from-blue-100 to-purple-100 rounded-xl flex items-center justify-center">
            <Package className="h-32 w-32 text-gray-400" />
          </div>

          <div>
            <div className="flex items-start justify-between mb-4">
              <div>
                <h1 className="text-3xl font-bold text-gray-900 mb-2">{product.name}</h1>
                <div className="flex items-center space-x-4">
                  <div className="flex items-center space-x-1">
                    <Star className="h-5 w-5 text-yellow-400 fill-yellow-400" />
                    <span className="font-semibold text-gray-900">
                      {parseFloat(product.average_rating).toFixed(1)}
                    </span>
                    <span className="text-gray-600">({product.total_reviews} reviews)</span>
                  </div>
                  {isOutOfStock && (
                    <Badge variant="destructive">Out of Stock</Badge>
                  )}
                </div>
              </div>
            </div>

            <p className="text-4xl font-bold text-gray-900 mb-6">
              {formatCurrency(product.price)}
            </p>

            <Card className="mb-6">
              <CardContent className="p-6">
                <h3 className="font-semibold text-gray-900 mb-3">Product Description</h3>
                <p className="text-gray-700 leading-relaxed">{product.description}</p>
              </CardContent>
            </Card>

            <Card className="mb-6">
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-3">
                    <Store className="h-5 w-5 text-gray-600" />
                    <div>
                      <p className="font-semibold text-gray-900">{product.storefront.name}</p>
                      {product.storefront.is_verified && (
                        <p className="text-sm text-blue-600 flex items-center space-x-1">
                          <Check className="h-4 w-4" />
                          <span>Verified Seller</span>
                        </p>
                      )}
                    </div>
                  </div>
                  <Link href={`/store/${product.storefront.slug}`}>
                    <button className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                      View Store
                    </button>
                  </Link>
                </div>
              </CardContent>
            </Card>

            {!isOutOfStock && (
              <Card className="mb-6">
                <CardContent className="p-6">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Quantity
                  </label>
                  <div className="flex items-center space-x-4">
                    <input
                      type="number"
                      value={quantity}
                      onChange={(e) => {
                        const val = parseInt(e.target.value) || 1;
                        setQuantity(Math.max(1, Math.min(val, product.stock_quantity)));
                      }}
                      min="1"
                      max={product.stock_quantity}
                      className="w-24 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    />
                    <span className="text-sm text-gray-600">
                      ({product.stock_quantity} available)
                    </span>
                  </div>
                </CardContent>
              </Card>
            )}

            <div className="space-y-3">
              <button
                onClick={handleAddToCart}
                disabled={!canAddToCart}
                className="w-full px-6 py-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <ShoppingCart className="h-5 w-5" />
                <span>{isOutOfStock ? 'Out of Stock' : 'Add to Cart'}</span>
              </button>

              <Link href="/cart">
                <button className="w-full px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold">
                  View Cart
                </button>
              </Link>
            </div>

            <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
              <div className="flex items-start space-x-3">
                <Shield className="h-5 w-5 text-green-600 mt-0.5" />
                <div>
                  <p className="font-semibold text-green-900 mb-1">Escrow Protection</p>
                  <p className="text-sm text-green-700">
                    Your payment is secured in escrow until you confirm delivery
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
