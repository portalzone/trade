'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { 
  Package,
  ArrowLeft,
  Plus,
  X,
  AlertCircle
} from 'lucide-react';
import Link from 'next/link';
import toast from 'react-hot-toast';

export default function AddProductPage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [mounted, setMounted] = useState(false);

  // Form state
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    price: '',
    stock_quantity: '',
    category: '',
    is_active: true,
  });

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    
    if (mounted && user && user.user_type !== 'SELLER') {
      toast.error('Only sellers can access this page');
      router.push('/dashboard');
    }
  }, [mounted, user]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // Validation
    if (!formData.name.trim()) {
      toast.error('Product name is required');
      return;
    }
    
    if (!formData.description.trim()) {
      toast.error('Product description is required');
      return;
    }
    
    if (!formData.price || parseFloat(formData.price) <= 0) {
      toast.error('Valid price is required');
      return;
    }
    
    if (!formData.stock_quantity || parseInt(formData.stock_quantity) < 0) {
      toast.error('Valid stock quantity is required');
      return;
    }

    setIsSubmitting(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      // Prepare data for API
      const productData = {
        name: formData.name.trim(),
        description: formData.description.trim(),
        price: parseFloat(formData.price),
        stock_quantity: parseInt(formData.stock_quantity),
        category: formData.category || null,
        is_active: formData.is_active,
      };

      console.log('Submitting product:', productData);

      const response = await fetch('http://localhost:8000/api/products', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(productData),
      });

      const data = await response.json();
      console.log('Create product response:', data);
      
      if (data.success) {
        toast.success('Product created successfully! 🎉');
        router.push('/my-store/products');
      } else {
        const errorMessage = data.message || data.error || 'Failed to create product';
        toast.error(errorMessage);
        console.error('API Error:', data);
      }
    } catch (error) {
      console.error('Error creating product:', error);
      toast.error('Failed to create product. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target;
    
    if (type === 'checkbox') {
      const checked = (e.target as HTMLInputElement).checked;
      setFormData(prev => ({ ...prev, [name]: checked }));
    } else {
      setFormData(prev => ({ ...prev, [name]: value }));
    }
  };

  if (!mounted) {
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
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <Link href="/my-store/products">
            <button className="mb-4 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
              <ArrowLeft className="h-5 w-5" />
              <span>Back to Products</span>
            </button>
          </Link>

          <div className="flex items-center space-x-3 mb-2">
            <Package className="h-8 w-8 text-blue-600" />
            <h1 className="text-3xl font-bold text-gray-900">Add New Product</h1>
          </div>
          <p className="text-gray-600">Create a new product listing for your store</p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>Product Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Product Name */}
              <div>
                <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
                  Product Name *
                </label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Wireless Bluetooth Headphones"
                  required
                />
                <p className="text-sm text-gray-500 mt-1">
                  Choose a clear, descriptive name for your product
                </p>
              </div>

              {/* Description */}
              <div>
                <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                  Description *
                </label>
                <textarea
                  id="description"
                  name="description"
                  value={formData.description}
                  onChange={handleChange}
                  rows={6}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Describe your product in detail. Include features, specifications, condition, and what makes it special..."
                  required
                />
                <p className="text-sm text-gray-500 mt-1">
                  Provide detailed information to help buyers make informed decisions
                </p>
              </div>

              {/* Price and Stock */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label htmlFor="price" className="block text-sm font-medium text-gray-700 mb-2">
                    Price (₦) *
                  </label>
                  <input
                    type="number"
                    id="price"
                    name="price"
                    value={formData.price}
                    onChange={handleChange}
                    step="0.01"
                    min="0"
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="0.00"
                    required
                  />
                  <p className="text-sm text-gray-500 mt-1">
                    Set a competitive price in Nigerian Naira
                  </p>
                </div>

                <div>
                  <label htmlFor="stock_quantity" className="block text-sm font-medium text-gray-700 mb-2">
                    Stock Quantity *
                  </label>
                  <input
                    type="number"
                    id="stock_quantity"
                    name="stock_quantity"
                    value={formData.stock_quantity}
                    onChange={handleChange}
                    min="0"
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="0"
                    required
                  />
                  <p className="text-sm text-gray-500 mt-1">
                    How many units do you have available?
                  </p>
                </div>
              </div>

              {/* Category */}
              <div>
                <label htmlFor="category" className="block text-sm font-medium text-gray-700 mb-2">
                  Category
                </label>
                <select
                  id="category"
                  name="category"
                  value={formData.category}
                  onChange={handleChange}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="">Select a category (optional)</option>
                  <option value="Electronics">Electronics</option>
                  <option value="Fashion">Fashion</option>
                  <option value="Home & Garden">Home & Garden</option>
                  <option value="Sports">Sports</option>
                  <option value="Books">Books</option>
                  <option value="Toys">Toys</option>
                  <option value="Furniture">Furniture</option>
                  <option value="Other">Other</option>
                </select>
                <p className="text-sm text-gray-500 mt-1">
                  Help buyers find your product by selecting a category
                </p>
              </div>

              {/* Active Status */}
              <div className="flex items-start space-x-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <input
                  type="checkbox"
                  id="is_active"
                  name="is_active"
                  checked={formData.is_active}
                  onChange={handleChange}
                  className="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <div>
                  <label htmlFor="is_active" className="text-sm font-medium text-gray-900">
                    Make product active immediately
                  </label>
                  <p className="text-sm text-gray-600 mt-1">
                    When checked, your product will be visible in the marketplace right away
                  </p>
                </div>
              </div>

              {/* Warnings */}
              {formData.stock_quantity && parseInt(formData.stock_quantity) === 0 && (
                <div className="flex items-start space-x-3 p-4 bg-red-50 border border-red-200 rounded-lg">
                  <AlertCircle className="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" />
                  <div>
                    <p className="text-sm font-medium text-red-900">Out of Stock</p>
                    <p className="text-sm text-red-700 mt-1">
                      This product will show as "Out of Stock" and won't be available for purchase
                    </p>
                  </div>
                </div>
              )}

              {formData.stock_quantity && parseInt(formData.stock_quantity) > 0 && parseInt(formData.stock_quantity) < 10 && (
                <div className="flex items-start space-x-3 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                  <AlertCircle className="h-5 w-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                  <div>
                    <p className="text-sm font-medium text-yellow-900">Low Stock Warning</p>
                    <p className="text-sm text-yellow-700 mt-1">
                      Products with low stock may need restocking soon
                    </p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Important Notes */}
          <Card className="mt-6 bg-gray-50 border-gray-200">
            <CardContent className="pt-6">
              <h3 className="font-semibold text-gray-900 mb-3">📋 Before You Submit:</h3>
              <ul className="space-y-2 text-sm text-gray-700">
                <li className="flex items-start space-x-2">
                  <span className="text-blue-600 font-bold">•</span>
                  <span>Make sure your product description is accurate and detailed</span>
                </li>
                <li className="flex items-start space-x-2">
                  <span className="text-blue-600 font-bold">•</span>
                  <span>Price your product competitively based on market rates</span>
                </li>
                <li className="flex items-start space-x-2">
                  <span className="text-blue-600 font-bold">•</span>
                  <span>Ensure you have sufficient stock before listing</span>
                </li>
                <li className="flex items-start space-x-2">
                  <span className="text-blue-600 font-bold">•</span>
                  <span>All transactions are protected by our escrow system</span>
                </li>
              </ul>
            </CardContent>
          </Card>

          {/* Action Buttons */}
          <div className="mt-6 flex items-center justify-end space-x-4">
            <Link href="/my-store/products">
              <button
                type="button"
                className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold flex items-center space-x-2"
              >
                <X className="h-5 w-5" />
                <span>Cancel</span>
              </button>
            </Link>

            <button
              type="submit"
              disabled={isSubmitting}
              className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? (
                <>
                  <Spinner size="sm" />
                  <span>Creating...</span>
                </>
              ) : (
                <>
                  <Plus className="h-5 w-5" />
                  <span>Create Product</span>
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
