'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

interface Wallet {
  id: number;
  available_balance: string;
  locked_escrow_funds: string;
  total_balance: number;
  wallet_status: string;
}

interface Transaction {
  id: number;
  transaction_type: string;
  amount: string;
  description: string;
  created_at: string;
  status: string;
}

export default function WalletPage() {
  const router = useRouter();
  const [wallet, setWallet] = useState<Wallet | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    
    if (!token) {
      router.push('/login');
      return;
    }

    fetchWalletData(token);
  }, [router]);

  const fetchWalletData = async (token: string) => {
    try {
      const response = await fetch('http://localhost:8000/api/wallet', {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });

      const data = await response.json();

      if (data.success) {
        setWallet(data.data.wallet);
        setTransactions(data.data.recent_transactions || []);
      } else {
        setError(data.error || 'Failed to load wallet');
      }
    } catch (error) {
      setError('Connection error');
      console.error('Fetch error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading wallet...</p>
        </div>
      </div>
    );
  }

  if (error || !wallet) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md">
          <p className="text-red-800">❌ {error || 'Wallet not found'}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-6xl mx-auto px-4">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">My Wallet</h1>
          <p className="text-gray-600 mt-2">Manage your funds and view transactions</p>
        </div>

        {/* Balance Cards */}
        <div className="grid md:grid-cols-3 gap-6 mb-8">
          <div className="bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 text-white">
            <p className="text-blue-100 text-sm mb-2">Available Balance</p>
            <p className="text-4xl font-bold">₦{parseFloat(wallet.available_balance).toLocaleString()}</p>
            <p className="text-blue-100 text-xs mt-2">Ready to use</p>
          </div>

          <div className="bg-gradient-to-br from-yellow-500 to-yellow-700 rounded-lg shadow-lg p-6 text-white">
            <p className="text-yellow-100 text-sm mb-2">Locked in Escrow</p>
            <p className="text-4xl font-bold">₦{parseFloat(wallet.locked_escrow_funds).toLocaleString()}</p>
            <p className="text-yellow-100 text-xs mt-2">Pending orders</p>
          </div>

          <div className="bg-gradient-to-br from-green-600 to-green-800 rounded-lg shadow-lg p-6 text-white">
            <p className="text-green-100 text-sm mb-2">Total Balance</p>
            <p className="text-4xl font-bold">₦{wallet.total_balance.toLocaleString()}</p>
            <p className="text-green-100 text-xs mt-2">Available + Locked</p>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="bg-white rounded-lg shadow-md p-6 mb-8">
          <h2 className="text-xl font-bold mb-4">Quick Actions</h2>
          <div className="grid md:grid-cols-3 gap-4">
            <Link href="/marketplace" className="bg-blue-50 border border-blue-200 rounded-lg p-4 hover:bg-blue-100 transition text-center">
              <p className="font-semibold text-blue-900">🛍️ Browse Marketplace</p>
              <p className="text-sm text-blue-700 mt-1">Find items to purchase</p>
            </Link>
            <Link href="/orders/create" className="bg-green-50 border border-green-200 rounded-lg p-4 hover:bg-green-100 transition text-center">
              <p className="font-semibold text-green-900">📦 Create Order</p>
              <p className="text-sm text-green-700 mt-1">List an item for sale</p>
            </Link>
            <button className="bg-gray-50 border border-gray-200 rounded-lg p-4 hover:bg-gray-100 transition text-center opacity-50 cursor-not-allowed">
              <p className="font-semibold text-gray-900">💳 Add Funds</p>
              <p className="text-sm text-gray-700 mt-1">Coming soon</p>
            </button>
          </div>
        </div>

        {/* Transaction History */}
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
          <div className="p-6 border-b">
            <h2 className="text-xl font-bold">Recent Transactions</h2>
          </div>

          {transactions.length === 0 ? (
            <div className="p-12 text-center text-gray-500">
              <p className="text-lg">No transactions yet</p>
              <p className="text-sm mt-2">Start buying or selling to see your transaction history</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {transactions.map((tx) => (
                    <tr key={tx.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 text-sm text-gray-900">
                        {new Date(tx.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 text-sm">
                        <span className={`px-2 py-1 rounded text-xs font-semibold ${
                          tx.transaction_type === 'CREDIT' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}>
                          {tx.transaction_type}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-600">{tx.description}</td>
                      <td className="px-6 py-4 text-sm text-right font-semibold">
                        ₦{parseFloat(tx.amount).toLocaleString()}
                      </td>
                      <td className="px-6 py-4 text-center">
                        <span className={`px-2 py-1 rounded text-xs font-semibold ${
                          tx.status === 'COMPLETED' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                        }`}>
                          {tx.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
