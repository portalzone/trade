'use client';

import { useState } from 'react';
import { X, CreditCard, AlertCircle } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import toast from 'react-hot-toast';

interface DepositModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function DepositModal({ isOpen, onClose, onSuccess }: DepositModalProps) {
  const [amount, setAmount] = useState('');
  const [gateway, setGateway] = useState<'paystack' | 'stripe'>('paystack');
  const [currency, setCurrency] = useState<'NGN' | 'USD'>('NGN');
  const [agreeToTerms, setAgreeToTerms] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);

  if (!isOpen) return null;

  const numAmount = parseFloat(amount) || 0;
  const minDeposit = currency === 'NGN' ? 100 : 1;
  const gatewayFee = gateway === 'paystack' 
    ? numAmount * 0.015 + 100 // 1.5% + ₦100
    : numAmount * 0.029 + 0.3; // 2.9% + $0.30
  const totalAmount = numAmount + gatewayFee;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (numAmount < minDeposit) {
      toast.error(`Minimum deposit is ${currency === 'NGN' ? '₦' : '$'}${minDeposit}`);
      return;
    }

    if (!agreeToTerms) {
      toast.error('Please agree to terms and conditions');
      return;
    }

    setIsProcessing(true);

    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/payments/deposit/initiate', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          amount: numAmount,
          gateway: gateway,
          currency: currency,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Redirect to payment gateway
        if (gateway === 'paystack' && data.data.authorization_url) {
          window.location.href = data.data.authorization_url;
        } else if (gateway === 'stripe' && data.data.client_secret) {
          // For Stripe, you'd integrate Stripe Elements here
          toast.success('Redirecting to Stripe...');
          // TODO: Implement Stripe payment flow
        }
      } else {
        toast.error(data.message || 'Failed to initiate deposit');
      }
    } catch (error) {
      console.error('Deposit error:', error);
      toast.error('Failed to process deposit');
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="p-6 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
              <CreditCard className="h-5 w-5 text-green-600" />
            </div>
            <h2 className="text-xl font-bold text-gray-900">Deposit Funds</h2>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition"
          >
            <X className="h-6 w-6" />
          </button>
        </div>

        {/* Content */}
        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {/* Gateway Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-3">
              Payment Gateway
            </label>
            <div className="grid grid-cols-2 gap-3">
              <button
                type="button"
                onClick={() => {
                  setGateway('paystack');
                  setCurrency('NGN');
                }}
                className={`p-4 border-2 rounded-lg transition ${
                  gateway === 'paystack'
                    ? 'border-blue-600 bg-blue-50'
                    : 'border-gray-300 hover:border-gray-400'
                }`}
              >
                <div className="text-center">
                  <p className="font-semibold text-gray-900">Paystack</p>
                  <p className="text-xs text-gray-600 mt-1">NGN only</p>
                </div>
              </button>

              <button
                type="button"
                onClick={() => {
                  setGateway('stripe');
                  setCurrency('USD');
                }}
                className={`p-4 border-2 rounded-lg transition ${
                  gateway === 'stripe'
                    ? 'border-blue-600 bg-blue-50'
                    : 'border-gray-300 hover:border-gray-400'
                }`}
              >
                <div className="text-center">
                  <p className="font-semibold text-gray-900">Stripe</p>
                  <p className="text-xs text-gray-600 mt-1">USD only</p>
                </div>
              </button>
            </div>
          </div>

          {/* Amount Input */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Amount ({currency})
            </label>
            <div className="relative">
              <span className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-semibold">
                {currency === 'NGN' ? '₦' : '$'}
              </span>
              <input
                type="number"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-semibold"
                placeholder="0.00"
                required
                min={minDeposit}
                step="0.01"
              />
            </div>
            <p className="text-sm text-gray-500 mt-1">
              Minimum: {currency === 'NGN' ? '₦' : '$'}{minDeposit}
            </p>
          </div>

          {/* Fee Breakdown */}
          {numAmount >= minDeposit && (
            <div className="bg-gray-50 rounded-lg p-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Deposit Amount:</span>
                <span className="font-semibold text-gray-900">
                  {currency === 'NGN' ? '₦' : '$'}{numAmount.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Gateway Fee:</span>
                <span className="font-semibold text-gray-900">
                  {currency === 'NGN' ? '₦' : '$'}{gatewayFee.toFixed(2)}
                </span>
              </div>
              <div className="border-t border-gray-300 pt-2 mt-2">
                <div className="flex justify-between">
                  <span className="font-semibold text-gray-900">Total to Pay:</span>
                  <span className="font-bold text-blue-600 text-lg">
                    {currency === 'NGN' ? '₦' : '$'}{totalAmount.toFixed(2)}
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* Terms & Conditions */}
          <div className="flex items-start space-x-3">
            <input
              type="checkbox"
              id="terms"
              checked={agreeToTerms}
              onChange={(e) => setAgreeToTerms(e.target.checked)}
              className="mt-1 h-4 w-4 text-blue-600 rounded"
            />
            <label htmlFor="terms" className="text-sm text-gray-600">
              I agree to the{' '}
              <a href="#" className="text-blue-600 hover:underline">
                terms and conditions
              </a>{' '}
              and understand that gateway fees apply
            </label>
          </div>

          {/* Info Alert */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start space-x-3">
            <AlertCircle className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <p className="text-sm text-blue-900">
              Your wallet will be credited immediately after successful payment
            </p>
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
              disabled={isProcessing || !agreeToTerms || numAmount < minDeposit}
              className="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
            >
              {isProcessing ? (
                <>
                  <Spinner size="sm" />
                  <span>Processing...</span>
                </>
              ) : (
                <span>Continue to Payment →</span>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
