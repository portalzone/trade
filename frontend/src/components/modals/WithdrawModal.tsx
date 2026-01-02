'use client';

import { useState, useEffect } from 'react';
import { X, Building2, Plus, AlertCircle, CheckCircle } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import toast from 'react-hot-toast';

interface BankAccount {
  id: number;
  bank_name: string;
  account_number: string;
  account_name: string;
  bank_code: string;
  is_primary: boolean;
}

interface WithdrawModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  availableBalance: number;
}

export function WithdrawModal({ isOpen, onClose, onSuccess, availableBalance }: WithdrawModalProps) {
  const [amount, setAmount] = useState('');
  const [selectedBank, setSelectedBank] = useState<number | null>(null);
  const [bankAccounts, setBankAccounts] = useState<BankAccount[]>([]);
  const [showAddBank, setShowAddBank] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [isLoadingBanks, setIsLoadingBanks] = useState(false);

  // Add bank form
  const [banks, setBanks] = useState<any[]>([]);
  const [newBankCode, setNewBankCode] = useState('');
  const [newAccountNumber, setNewAccountNumber] = useState('');
  const [accountName, setAccountName] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);

  useEffect(() => {
    if (isOpen) {
      fetchBankAccounts();
      fetchBanks();
    }
  }, [isOpen]);

  const fetchBankAccounts = async () => {
    setIsLoadingBanks(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/bank-accounts', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      if (data.success) {
        setBankAccounts(data.data || []);
        if (data.data?.length > 0) {
          // Select primary or first account
          const primary = data.data.find((acc: BankAccount) => acc.is_primary);
          setSelectedBank(primary?.id || data.data[0].id);
        }
      }
    } catch (error) {
      console.error('Error fetching bank accounts:', error);
    } finally {
      setIsLoadingBanks(false);
    }
  };

  const fetchBanks = async () => {
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/payments/banks', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      if (data.success) {
        // Remove duplicates based on bank code
        const uniqueBanks = Array.from(
          new Map((data.data || []).map((bank: any) => [bank.code, bank])).values()
        );
        setBanks(uniqueBanks);
      }
    } catch (error) {
      console.error('Error fetching banks:', error);
    }
  };

  const verifyBankAccount = async () => {
    if (!newBankCode || !newAccountNumber || newAccountNumber.length !== 10) {
      toast.error('Please enter a valid 10-digit account number');
      return;
    }

    setIsVerifying(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/payments/verify-bank-account', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          account_number: newAccountNumber,
          bank_code: newBankCode,
        }),
      });

      const data = await response.json();
      if (data.success) {
        setAccountName(data.data.account_name);
        toast.success('Account verified!');
      } else {
        toast.error(data.message || 'Verification failed');
      }
    } catch (error) {
      console.error('Verification error:', error);
      toast.error('Failed to verify account');
    } finally {
      setIsVerifying(false);
    }
  };

  const addBankAccount = async () => {
    if (!accountName) {
      toast.error('Please verify your account first');
      return;
    }

    try {
      const token = localStorage.getItem('auth_token');
      const selectedBankData = banks.find(b => b.code === newBankCode);
      
      const response = await fetch('http://localhost:8000/api/bank-accounts', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          bank_name: selectedBankData?.name,
          bank_code: newBankCode,
          account_number: newAccountNumber,
          account_name: accountName,
        }),
      });

      const data = await response.json();
      if (data.success) {
        toast.success('Bank account added successfully!');
        setShowAddBank(false);
        setNewBankCode('');
        setNewAccountNumber('');
        setAccountName('');
        fetchBankAccounts();
      } else {
        toast.error(data.message || 'Failed to add bank account');
      }
    } catch (error) {
      console.error('Add bank error:', error);
      toast.error('Failed to add bank account');
    }
  };

  const handleWithdraw = async (e: React.FormEvent) => {
    e.preventDefault();

    const numAmount = parseFloat(amount);
    
    if (!selectedBank) {
      toast.error('Please select a bank account');
      return;
    }

    if (numAmount < 100) {
      toast.error('Minimum withdrawal is ₦100');
      return;
    }

    if (numAmount > availableBalance) {
      toast.error('Insufficient balance');
      return;
    }

    setIsProcessing(true);

    try {
      const token = localStorage.getItem('auth_token');
      const account = bankAccounts.find(acc => acc.id === selectedBank);
      
      const response = await fetch('http://localhost:8000/api/payments/withdraw/initiate', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          amount: numAmount,
          gateway: 'paystack',
          bank_details: {
            account_number: account?.account_number,
            account_name: account?.account_name,
            bank_code: account?.bank_code,
            bank_name: account?.bank_name,
          },
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Withdrawal initiated successfully! Processing may take 1-2 business days.');
        onSuccess();
        onClose();
      } else {
        toast.error(data.message || 'Withdrawal failed');
      }
    } catch (error) {
      console.error('Withdrawal error:', error);
      toast.error('Failed to process withdrawal');
    } finally {
      setIsProcessing(false);
    }
  };

  if (!isOpen) return null;

  const numAmount = parseFloat(amount) || 0;
  const flatFee = 50;
  const percentageFee = numAmount * 0.005; // 0.5%
  const totalFee = flatFee + percentageFee;
  const netAmount = numAmount - totalFee;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="p-6 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
              <Building2 className="h-5 w-5 text-blue-600" />
            </div>
            <h2 className="text-xl font-bold text-gray-900">Withdraw to Bank</h2>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition"
          >
            <X className="h-6 w-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {isLoadingBanks ? (
            <div className="text-center py-8">
              <Spinner size="lg" />
              <p className="text-gray-600 mt-4">Loading bank accounts...</p>
            </div>
          ) : !showAddBank ? (
            <form onSubmit={handleWithdraw} className="space-y-6">
              {/* Bank Account Selection */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Select Bank Account
                </label>
                {bankAccounts.length > 0 ? (
                  <div className="space-y-2">
                    {bankAccounts.map((account) => (
                      <button
                        key={account.id}
                        type="button"
                        onClick={() => setSelectedBank(account.id)}
                        className={`w-full p-4 border-2 rounded-lg transition text-left ${
                          selectedBank === account.id
                            ? 'border-blue-600 bg-blue-50'
                            : 'border-gray-300 hover:border-gray-400'
                        }`}
                      >
                        <div className="flex items-start justify-between">
                          <div>
                            <p className="font-semibold text-gray-900">{account.bank_name}</p>
                            <p className="text-sm text-gray-600">{account.account_number}</p>
                            <p className="text-xs text-gray-500 mt-1">{account.account_name}</p>
                          </div>
                          {account.is_primary && (
                            <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                              Primary
                            </span>
                          )}
                        </div>
                      </button>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 bg-gray-50 rounded-lg">
                    <Building2 className="h-12 w-12 text-gray-300 mx-auto mb-3" />
                    <p className="text-gray-600 mb-2">No bank accounts added</p>
                    <p className="text-sm text-gray-500">Add a bank account to withdraw funds</p>
                  </div>
                )}
                
                <button
                  type="button"
                  onClick={() => setShowAddBank(true)}
                  className="mt-3 w-full px-4 py-2 border border-dashed border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center justify-center space-x-2"
                >
                  <Plus className="h-4 w-4" />
                  <span>Add New Bank Account</span>
                </button>
              </div>

              {/* Amount Input */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Withdrawal Amount (NGN)
                </label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-semibold">
                    ₦
                  </span>
                  <input
                    type="number"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    className="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-semibold"
                    placeholder="0.00"
                    required
                    min="100"
                    max={availableBalance}
                    step="0.01"
                  />
                </div>
                <p className="text-sm text-gray-500 mt-1">
                  Available: ₦{availableBalance.toLocaleString()}
                </p>
              </div>

              {/* Fee Breakdown */}
              {numAmount >= 100 && (
                <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-600">Withdrawal Amount:</span>
                    <span className="font-semibold text-gray-900">₦{numAmount.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-600">Service Fee:</span>
                    <span className="font-semibold text-gray-900">₦{totalFee.toFixed(2)}</span>
                  </div>
                  <div className="border-t border-gray-300 pt-2 mt-2">
                    <div className="flex justify-between">
                      <span className="font-semibold text-gray-900">You'll Receive:</span>
                      <span className="font-bold text-green-600 text-lg">
                        ₦{netAmount.toFixed(2)}
                      </span>
                    </div>
                  </div>
                </div>
              )}

              {/* Processing Time Notice */}
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 flex items-start space-x-3">
                <AlertCircle className="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                <div className="text-sm text-yellow-900">
                  <p className="font-semibold mb-1">Processing Time</p>
                  <p>Withdrawals typically take 1-2 business days to reflect in your bank account</p>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex space-x-3">
                <button
                  type="button"
                  onClick={onClose}
                  disabled={isProcessing}
                  className="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold disabled:opacity-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isProcessing || !selectedBank || numAmount < 100 || numAmount > availableBalance}
                  className="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                >
                  {isProcessing ? (
                    <>
                      <Spinner size="sm" />
                      <span>Processing...</span>
                    </>
                  ) : (
                    <span>Confirm Withdrawal</span>
                  )}
                </button>
              </div>
            </form>
          ) : (
            /* Add Bank Account Form */
            <div className="space-y-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Select Bank
                </label>
                <select
                  value={newBankCode}
                  onChange={(e) => setNewBankCode(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  required
                >
                  <option value="">Choose a bank</option>
                  {banks.map((bank, index) => (
                    <option key={`${bank.code}-${index}`} value={bank.code}>
                      {bank.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Account Number
                </label>
                <input
                  type="text"
                  value={newAccountNumber}
                  onChange={(e) => setNewAccountNumber(e.target.value.replace(/\D/g, '').slice(0, 10))}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="0123456789"
                  maxLength={10}
                  required
                />
              </div>

              {newBankCode && newAccountNumber.length === 10 && !accountName && (
                <button
                  type="button"
                  onClick={verifyBankAccount}
                  disabled={isVerifying}
                  className="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold disabled:opacity-50 flex items-center justify-center space-x-2"
                >
                  {isVerifying ? (
                    <>
                      <Spinner size="sm" />
                      <span>Verifying...</span>
                    </>
                  ) : (
                    <span>Verify Account</span>
                  )}
                </button>
              )}

              {accountName && (
                <div className="bg-green-50 border border-green-200 rounded-lg p-4 flex items-start space-x-3">
                  <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-green-900">Account Verified</p>
                    <p className="text-sm text-green-800 mt-1">{accountName}</p>
                  </div>
                </div>
              )}

              <div className="flex space-x-3">
                <button
                  type="button"
                  onClick={() => {
                    setShowAddBank(false);
                    setNewBankCode('');
                    setNewAccountNumber('');
                    setAccountName('');
                  }}
                  className="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold"
                >
                  Back
                </button>
                <button
                  type="button"
                  onClick={addBankAccount}
                  disabled={!accountName}
                  className="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Add Account
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
