#!/bin/bash

echo "Creating functional login page..."
cat > src/app/login/page.tsx << 'EOF'
'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function LoginPage() {
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(false);
  const [formData, setFormData] = useState({ email: '', password: '' });
  const [message, setMessage] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setMessage('');

    try {
      const response = await fetch('http://localhost:8000/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });
      const data = await response.json();
      if (data.success) {
        localStorage.setItem('auth_token', data.data.token);
        localStorage.setItem('user', JSON.stringify(data.data.user));
        setMessage('✅ Login successful! Redirecting...');
        setTimeout(() => router.push('/dashboard'), 1000);
      } else {
        setMessage('❌ ' + (data.error || 'Login failed'));
      }
    } catch (error) {
      setMessage('❌ Connection error');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
      <div className="bg-white rounded-lg shadow-lg p-8 w-full max-w-md">
        <h1 className="text-3xl font-bold text-center mb-6">Login to T-Trade</h1>
        {message && (
          <div className={`mb-4 p-3 rounded ${message.includes('✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
            {message}
          </div>
        )}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" value={formData.email} onChange={(e) => setFormData({...formData, email: e.target.value})} className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="your@email.com" required />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" value={formData.password} onChange={(e) => setFormData({...formData, password: e.target.value})} className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="••••••••" required />
          </div>
          <button type="submit" disabled={isLoading} className="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 font-semibold transition disabled:opacity-50">
            {isLoading ? 'Logging in...' : 'Login'}
          </button>
        </form>
        <p className="text-center mt-6 text-gray-600">
          Don't have an account? <a href="/register" className="text-blue-600 hover:underline font-semibold">Sign up</a>
        </p>
      </div>
    </div>
  );
}
EOF

echo "✅ Login page created"

echo "Creating dashboard page..."
mkdir -p src/app/dashboard
cat > src/app/dashboard/page.tsx << 'EOF'
'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState<any>(null);

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    const token = localStorage.getItem('auth_token');
    if (!token || !storedUser) {
      router.push('/login');
      return;
    }
    setUser(JSON.parse(storedUser));
  }, [router]);

  const handleLogout = () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    router.push('/');
  };

  if (!user) return <div className="min-h-screen flex items-center justify-center">Loading...</div>;

  return (
    <div className="min-h-screen bg-gray-50 py-12 px-4">
      <div className="max-w-4xl mx-auto">
        <div className="bg-white rounded-lg shadow-lg p-8">
          <div className="flex justify-between items-center mb-6">
            <h1 className="text-3xl font-bold">Dashboard</h1>
            <button onClick={handleLogout} className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</button>
          </div>
          <div className="border-t pt-6">
            <h2 className="text-xl font-semibold mb-4">Welcome, {user.full_name}! 🎉</h2>
            <div className="grid grid-cols-2 gap-4 mt-6">
              <div className="bg-gray-50 p-4 rounded-lg"><p className="text-sm text-gray-600">Email</p><p className="font-semibold">{user.email}</p></div>
              <div className="bg-gray-50 p-4 rounded-lg"><p className="text-sm text-gray-600">Username</p><p className="font-semibold">{user.username}</p></div>
              <div className="bg-gray-50 p-4 rounded-lg"><p className="text-sm text-gray-600">Type</p><p className="font-semibold">{user.user_type}</p></div>
              <div className="bg-gray-50 p-4 rounded-lg"><p className="text-sm text-gray-600">Phone</p><p className="font-semibold">{user.phone_number}</p></div>
            </div>
            <div className="mt-8 p-4 bg-green-50 border border-green-200 rounded-lg">
              <p className="text-green-800">✅ Authentication working! You're logged in.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
EOF

echo "✅ Dashboard created"
echo "🎉 All files created successfully!"
