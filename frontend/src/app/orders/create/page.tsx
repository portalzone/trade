'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

export default function CreateOrderPage() {
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(false);
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    price: '',
    currency: 'NGN',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState('');

  useEffect(() => {
    // Check if user is logged in
    const token = localStorage.getItem('auth_token');
    if (!token) {
      router.push('/login');
    }
  }, [router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setErrors({});
    setMessage('');

    const token = localStorage.getItem('auth_token');

    try {
      const response = await fetch('http://localhost:8000/api/orders', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();

      if (data.success) {
        setMessage('✅ Order created successfully!');
        setTimeout(() => {
          router.push('/marketplace');
        }, 1500);
      } else {
        setMessage('❌ ' + (data.error || data.message || 'Failed to create order'));
        
        if (data.errors) {
          const formattedErrors: Record<string, string> = {};
          Object.keys(data.errors).forEach((key) => {
            formattedErrors[key] = data.errors[key][0];
          });
          setErrors(formattedErrors);
        }
      }
    } catch (error) {
      setMessage('❌ Connection error');
      console.error('Create order error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 py-12 px-4">
      <div className="max-w-2xl mx-auto">
        <div className="mb-6">
          <Link href="/marketplace" className="text-blue-600 hover:underline flex items-center gap-2">
            ← Back to Marketplace
          </Link>
        </div>

        <div className="bg-white rounded-lg shadow-lg p-8">
          <h1 className="text-3xl font-bold text-center mb-2">Create New Order</h1>
          <p className="text-center text-gray-600 mb-6">List an item for sale</p>

          {message && (
            <div className={`mb-6 p-4 rounded ${
              message.includes('✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`}>
              {message}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Title <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g. iPhone 13 Pro Max"
                required
              />
              {errors.title && <p className="mt-1 text-sm text-red-600">{errors.title}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Description <span className="text-red-500">*</span>
              </label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                rows={4}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Describe your item in detail..."
                required
              />
              {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Price (₦) <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                value={formData.price}
                onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="5000"
                min="500"
                step="0.01"
                required
              />
              {errors.price && <p className="mt-1 text-sm text-red-600">{errors.price}</p>}
              <p className="mt-1 text-sm text-gray-500">Minimum price: ₦500</p>
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h3 className="font-semibold text-blue-900 mb-2">How it works:</h3>
              <ul className="text-sm text-blue-800 space-y-1">
                <li>• Your order will be listed on the marketplace</li>
                <li>• Buyers can purchase with escrow protection</li>
                <li>• Platform fee: 2.5% (deducted when order completes)</li>
                <li>• Funds released after buyer confirms delivery</li>
              </ul>
            </div>

            <div className="flex gap-4">
              <button
                type="button"
                onClick={() => router.push('/marketplace')}
                className="flex-1 bg-gray-200 text-gray-800 py-3 rounded-lg hover:bg-gray-300 font-semibold transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isLoading}
                className="flex-1 bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold transition disabled:opacity-50"
              >
                {isLoading ? 'Creating...' : 'Create Order'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
