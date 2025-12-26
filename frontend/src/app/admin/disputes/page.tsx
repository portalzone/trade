'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

interface Dispute {
  id: number;
  order_id: number;
  raised_by_user_id: number;
  dispute_reason: string;
  dispute_status: string;
  resolution_details: string | null;
  resolved_at: string | null;
  created_at: string;
  order: {
    id: number;
    title: string;
    price: string;
  };
  raised_by: {
    full_name: string;
    email: string;
  };
}

export default function AdminDisputesPage() {
  const router = useRouter();
  const [disputes, setDisputes] = useState<Dispute[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedDispute, setSelectedDispute] = useState<Dispute | null>(null);
  const [resolutionType, setResolutionType] = useState('');
  const [adminNotes, setAdminNotes] = useState('');
  const [isResolving, setIsResolving] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    const user = localStorage.getItem('user');
    
    if (!token) {
      router.push('/login');
      return;
    }

    const userData = user ? JSON.parse(user) : null;
    if (userData?.user_type !== 'ADMIN') {
      setError('Access denied. Admin only.');
      return;
    }

    fetchDisputes(token);
  }, [router]);

  const fetchDisputes = async (token: string) => {
    try {
      const response = await fetch('http://localhost:8000/api/admin/disputes', {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });

      const data = await response.json();

      if (data.success) {
        setDisputes(data.data.data || []);
      } else {
        setError(data.error || 'Failed to load disputes');
      }
    } catch (error) {
      setError('Connection error');
      console.error('Fetch error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleResolve = async () => {
    if (!selectedDispute || !resolutionType) {
      alert('Please select a resolution type');
      return;
    }

    if (!adminNotes.trim()) {
      alert('Please provide admin notes');
      return;
    }

    setIsResolving(true);
    setMessage('');

    const token = localStorage.getItem('auth_token');
    const orderPrice = parseFloat(selectedDispute.order.price);

    // Build request body
    const body: any = {
      resolution: resolutionType,
      admin_notes: adminNotes.trim(),
    };

    // For partial resolution, calculate amounts (50/50 split)
    if (resolutionType === 'partial') {
      body.buyer_amount = orderPrice / 2;
      body.seller_amount = orderPrice / 2;
    }

    try {
      const response = await fetch(`http://localhost:8000/api/admin/disputes/${selectedDispute.id}/resolve`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify(body),
      });

      const data = await response.json();

      if (data.success) {
        setMessage('✅ Dispute resolved successfully!');
        setSelectedDispute(null);
        setResolutionType('');
        setAdminNotes('');
        fetchDisputes(token!);
      } else {
        setMessage('❌ ' + (data.error || data.message || 'Failed to resolve dispute'));
      }
    } catch (error) {
      setMessage('❌ Connection error');
      console.error('Resolve error:', error);
    } finally {
      setIsResolving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading disputes...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md text-center">
          <p className="text-red-800 mb-4">❌ {error}</p>
          <Link href="/dashboard" className="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
            Back to Dashboard
          </Link>
        </div>
      </div>
    );
  }

  const pendingDisputes = disputes.filter(d => d.dispute_status === 'OPEN' || d.dispute_status === 'UNDER_REVIEW');
  const resolvedDisputes = disputes.filter(d => d.dispute_status.startsWith('RESOLVED_'));

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Admin - Dispute Management</h1>
          <p className="text-gray-600 mt-2">Review and resolve order disputes</p>
        </div>

        {message && (
          <div className={`mb-6 p-4 rounded ${message.includes('✅') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
            {message}
          </div>
        )}

        <div className="grid md:grid-cols-3 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow-md p-6">
            <p className="text-sm text-gray-600 mb-2">Total Disputes</p>
            <p className="text-3xl font-bold text-blue-600">{disputes.length}</p>
          </div>
          <div className="bg-white rounded-lg shadow-md p-6">
            <p className="text-sm text-gray-600 mb-2">Pending</p>
            <p className="text-3xl font-bold text-yellow-600">{pendingDisputes.length}</p>
          </div>
          <div className="bg-white rounded-lg shadow-md p-6">
            <p className="text-sm text-gray-600 mb-2">Resolved</p>
            <p className="text-3xl font-bold text-green-600">{resolvedDisputes.length}</p>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md mb-8">
          <div className="p-6 border-b">
            <h2 className="text-xl font-bold">Pending Disputes ({pendingDisputes.length})</h2>
          </div>

          {pendingDisputes.length === 0 ? (
            <div className="p-12 text-center text-gray-500">
              <p className="text-lg">No pending disputes</p>
            </div>
          ) : (
            <div className="divide-y">
              {pendingDisputes.map((dispute) => (
                <div key={dispute.id} className="p-6 hover:bg-gray-50">
                  <div className="flex justify-between items-start mb-4">
                    <div className="flex-1">
                      <h3 className="text-lg font-bold text-gray-900 mb-2">
                        Dispute #{dispute.id} - Order #{dispute.order.id}
                      </h3>
                      <p className="text-gray-700 mb-2"><strong>Order:</strong> {dispute.order.title}</p>
                      <p className="text-gray-700 mb-2"><strong>Amount:</strong> ₦{parseFloat(dispute.order.price).toLocaleString()}</p>
                      <p className="text-gray-700 mb-2"><strong>Raised by:</strong> {dispute.raised_by.full_name} ({dispute.raised_by.email})</p>
                      <p className="text-gray-700 mb-2"><strong>Reason:</strong> {dispute.dispute_reason}</p>
                      <p className="text-sm text-gray-500">Created: {new Date(dispute.created_at).toLocaleString()}</p>
                    </div>
                    <span className="px-4 py-2 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800">
                      {dispute.dispute_status}
                    </span>
                  </div>

                  <button onClick={() => setSelectedDispute(dispute)} className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-semibold">
                    Resolve Dispute
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {resolvedDisputes.length > 0 && (
          <div className="bg-white rounded-lg shadow-md">
            <div className="p-6 border-b">
              <h2 className="text-xl font-bold">Resolved Disputes ({resolvedDisputes.length})</h2>
            </div>
            <div className="divide-y">
              {resolvedDisputes.map((dispute) => (
                <div key={dispute.id} className="p-6">
                  <div className="flex justify-between items-start">
                    <div>
                      <h3 className="text-lg font-bold text-gray-900 mb-2">
                        Dispute #{dispute.id} - Order #{dispute.order.id}
                      </h3>
                      <p className="text-gray-700 mb-1"><strong>Order:</strong> {dispute.order.title}</p>
                      <p className="text-gray-700 mb-1"><strong>Resolution:</strong> {dispute.resolution_details}</p>
                      <p className="text-sm text-gray-500">Resolved: {dispute.resolved_at ? new Date(dispute.resolved_at).toLocaleString() : 'N/A'}</p>
                    </div>
                    <span className="px-4 py-2 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                      {dispute.dispute_status}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {selectedDispute && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
            <div className="bg-white rounded-lg shadow-xl p-8 max-w-2xl w-full mx-4 my-8">
              <h2 className="text-2xl font-bold mb-6">Resolve Dispute #{selectedDispute.id}</h2>

              <div className="mb-6 bg-gray-50 p-4 rounded-lg">
                <p className="mb-2"><strong>Order:</strong> {selectedDispute.order.title}</p>
                <p className="mb-2"><strong>Amount:</strong> ₦{parseFloat(selectedDispute.order.price).toLocaleString()}</p>
                <p className="mb-2"><strong>Raised by:</strong> {selectedDispute.raised_by.full_name}</p>
                <p className="mb-2"><strong>Reason:</strong> {selectedDispute.dispute_reason}</p>
              </div>

              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-3">Resolution Type:</label>
                <div className="space-y-3">
                  <label className="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="resolution" value="buyer" checked={resolutionType === 'buyer'} onChange={(e) => setResolutionType(e.target.value)} className="mr-3" />
                    <div>
                      <p className="font-semibold">Refund to Buyer</p>
                      <p className="text-sm text-gray-600">Full refund - Buyer wins dispute</p>
                    </div>
                  </label>

                  <label className="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="resolution" value="seller" checked={resolutionType === 'seller'} onChange={(e) => setResolutionType(e.target.value)} className="mr-3" />
                    <div>
                      <p className="font-semibold">Release to Seller</p>
                      <p className="text-sm text-gray-600">Release payment - Seller wins dispute</p>
                    </div>
                  </label>

                  <label className="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="resolution" value="partial" checked={resolutionType === 'partial'} onChange={(e) => setResolutionType(e.target.value)} className="mr-3" />
                    <div>
                      <p className="font-semibold">Partial Refund (50/50)</p>
                      <p className="text-sm text-gray-600">Split amount between buyer and seller</p>
                    </div>
                  </label>
                </div>
              </div>

              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-2">Admin Notes (Required):</label>
                <textarea value={adminNotes} onChange={(e) => setAdminNotes(e.target.value)} rows={4} className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Explain the resolution decision..." />
              </div>

              <div className="flex gap-4">
                <button onClick={() => { setSelectedDispute(null); setResolutionType(''); setAdminNotes(''); }} className="flex-1 bg-gray-200 text-gray-800 py-3 rounded-lg hover:bg-gray-300 font-semibold">
                  Cancel
                </button>
                <button onClick={handleResolve} disabled={isResolving || !resolutionType || !adminNotes.trim()} className="flex-1 bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold disabled:opacity-50">
                  {isResolving ? 'Resolving...' : 'Resolve Dispute'}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
