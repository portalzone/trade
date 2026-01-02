'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Wallet, 
  TrendingUp, 
  TrendingDown,
  Lock,
  Plus,
  ArrowDownToLine,
  ArrowUpFromLine,
  RefreshCw,
  Eye,
  EyeOff,
  Download,
  Filter
} from 'lucide-react';
import { formatCurrency, formatDate } from '@/lib/utils';
import toast from 'react-hot-toast';
import { DepositModal } from '@/components/modals/DepositModal';
import { WithdrawModal } from '@/components/modals/WithdrawModal';

interface Transaction {
  id: number;
  type: string;
  amount: string;
  description: string;
  status: string;
  date: string;
  reference: string;
}

export default function WalletPage() {
  const router = useRouter();
  const { user, wallet, setWallet } = useAuthStore();
  const [mounted, setMounted] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [showBalance, setShowBalance] = useState(true);
  
  // Modal states
  const [showDepositModal, setShowDepositModal] = useState(false);
  const [showWithdrawModal, setShowWithdrawModal] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
    }
  }, [mounted, user, router]);

  useEffect(() => {
    if (user) {
      fetchWalletData();
    }
  }, [user]);

  const fetchWalletData = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      // Fetch wallet info
      const walletRes = await fetch('http://localhost:8000/api/wallet', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      
      const walletData = await walletRes.json();
      if (walletData.success) {
        setWallet(walletData.data.wallet);
      }

      // Fetch transactions
      const txRes = await fetch('http://localhost:8000/api/wallet/transactions?limit=20', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      
      const txData = await txRes.json();
      if (txData.success) {
        setTransactions(txData.data.transactions || []);
      }
    } catch (error) {
      console.error('Error fetching wallet:', error);
      toast.error('Failed to load wallet data');
    } finally {
      setIsLoading(false);
    }
  };

  const handleDepositSuccess = () => {
    fetchWalletData();
    toast.success('Deposit successful!');
  };

  const handleWithdrawSuccess = () => {
    fetchWalletData();
    toast.success('Withdrawal initiated successfully!');
  };

  if (!mounted || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  const getTransactionIcon = (type: string) => {
    if (type.includes('DEPOSIT') || type.includes('CREDIT')) {
      return <ArrowDownToLine className="h-5 w-5 text-green-600" />;
    }
    return <ArrowUpFromLine className="h-5 w-5 text-red-600" />;
  };

  const getStatusBadge = (status: string) => {
    const variants = {
      'COMPLETED': 'success',
      'PENDING': 'warning',
      'FAILED': 'destructive',
      'PROCESSING': 'default',
    } as const;
    
    return <Badge variant={variants[status as keyof typeof variants] || 'secondary'}>{status}</Badge>;
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 flex items-center space-x-3">
              <Wallet className="h-8 w-8 text-blue-600" />
              <span>My Wallet</span>
            </h1>
            <p className="text-gray-600 mt-1">
              Manage your funds and view transaction history
            </p>
          </div>
          <div className="mt-4 md:mt-0 flex items-center space-x-3">
            <button
              onClick={fetchWalletData}
              disabled={isLoading}
              className="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center space-x-2"
            >
              <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
              <span>Refresh</span>
            </button>
          </div>
        </div>

        {/* Balance Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* Available Balance */}
          <Card className="border-l-4 border-l-green-500 bg-gradient-to-br from-green-50 to-white hover:shadow-xl transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center justify-between">
                <span>Available Balance</span>
                <button 
                  onClick={() => setShowBalance(!showBalance)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  {showBalance ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
                </button>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <p className="text-4xl font-bold text-gray-900">
                  {showBalance ? formatCurrency(wallet?.available_balance || 0) : '••••••'}
                </p>
                <p className="text-sm text-green-600 flex items-center">
                  <TrendingUp className="h-4 w-4 mr-1" />
                  Available to spend
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Locked Funds */}
          <Card className="border-l-4 border-l-orange-500 bg-gradient-to-br from-orange-50 to-white hover:shadow-xl transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center justify-between">
                <span>Locked Funds</span>
                <Lock className="h-5 w-5 text-orange-500" />
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <p className="text-4xl font-bold text-gray-900">
                  {showBalance ? formatCurrency(wallet?.locked_escrow_funds || 0) : '••••••'}
                </p>
                <p className="text-sm text-orange-600">
                  In escrow protection
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Total Balance */}
          <Card className="border-l-4 border-l-blue-500 bg-gradient-to-br from-blue-50 to-white hover:shadow-xl transition">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 flex items-center justify-between">
                <span>Total Balance</span>
                <TrendingUp className="h-5 w-5 text-blue-500" />
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <p className="text-4xl font-bold text-gray-900">
                  {showBalance ? formatCurrency(wallet?.total_balance || 0) : '••••••'}
                </p>
                <p className="text-sm text-blue-600">
                  Total wallet value
                </p>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Quick Actions */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button 
            onClick={() => setShowDepositModal(true)}
            className="p-6 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-700 transition shadow-lg hover:shadow-xl group"
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <div className="h-12 w-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                  <Plus className="h-6 w-6" />
                </div>
                <div className="text-left">
                  <p className="font-semibold text-lg">Deposit Funds</p>
                  <p className="text-sm text-green-100">Add money to your wallet</p>
                </div>
              </div>
              <ArrowDownToLine className="h-6 w-6" />
            </div>
          </button>

          <button 
            onClick={() => setShowWithdrawModal(true)}
            className="p-6 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:from-blue-600 hover:to-blue-700 transition shadow-lg hover:shadow-xl group"
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <div className="h-12 w-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                  <ArrowUpFromLine className="h-6 w-6" />
                </div>
                <div className="text-left">
                  <p className="font-semibold text-lg">Withdraw Funds</p>
                  <p className="text-sm text-blue-100">Transfer to your bank</p>
                </div>
              </div>
              <ArrowUpFromLine className="h-6 w-6" />
            </div>
          </button>
        </div>

        {/* Transaction History */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center space-x-2">
                <RefreshCw className="h-5 w-5 text-gray-600" />
                <span>Transaction History</span>
              </CardTitle>
              <div className="flex items-center space-x-2">
                <button className="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center space-x-1">
                  <Filter className="h-4 w-4" />
                  <span>Filter</span>
                </button>
                <button className="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center space-x-1">
                  <Download className="h-4 w-4" />
                  <span>Export</span>
                </button>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="text-center py-12">
                <Spinner size="lg" />
              </div>
            ) : transactions.length === 0 ? (
              <div className="text-center py-12">
                <Wallet className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                <p className="text-gray-500 font-medium">No transactions yet</p>
                <p className="text-sm text-gray-400 mt-1">
                  Your transaction history will appear here
                </p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-gray-200">
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-600">Type</th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-600">Description</th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-600">Amount</th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {transactions.map((tx) => (
                      <tr key={tx.id} className="border-b border-gray-100 hover:bg-gray-50 transition">
                        <td className="py-4 px-4">
                          <div className="flex items-center space-x-3">
                            <div className="h-10 w-10 bg-gray-100 rounded-lg flex items-center justify-center">
                              {getTransactionIcon(tx.type)}
                            </div>
                            <span className="font-medium text-gray-900">{tx.type}</span>
                          </div>
                        </td>
                        <td className="py-4 px-4 text-gray-600">{tx.description}</td>
                        <td className="py-4 px-4">
                          <span className={`font-semibold ${
                            tx.type.includes('DEPOSIT') || tx.type.includes('CREDIT')
                              ? 'text-green-600'
                              : 'text-red-600'
                          }`}>
                            {tx.type.includes('DEPOSIT') || tx.type.includes('CREDIT') ? '+' : '-'}
                            {formatCurrency(tx.amount)}
                          </span>
                        </td>
                        <td className="py-4 px-4 text-sm text-gray-500">
                          {formatDate(tx.date)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Modals */}
      <DepositModal 
        isOpen={showDepositModal}
        onClose={() => setShowDepositModal(false)}
        onSuccess={handleDepositSuccess}
      />
      
      <WithdrawModal 
        isOpen={showWithdrawModal}
        onClose={() => setShowWithdrawModal(false)}
        onSuccess={handleWithdrawSuccess}
        availableBalance={wallet?.available_balance || 0}
      />
    </div>
  );
}
