'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Shield, Lock, Mail, Key } from 'lucide-react';
import toast from 'react-hot-toast';

export default function AdminLoginPage() {
  const router = useRouter();
  const setAdminAuth = useAdminAuthStore((state) => state.setAdminAuth);
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [mfaCode, setMfaCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [showMfaInput, setShowMfaInput] = useState(false);
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    try {
      const body: any = { email, password };
      
      if (showMfaInput) {
        if (useRecoveryCode) {
          body.recovery_code = recoveryCode;
        } else {
          body.mfa_code = mfaCode;
        }
      }

      const response = await fetch('http://localhost:8000/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      const data = await response.json();

      if (data.success) {
        // Check if user is admin
        if (data.data.user.user_type !== 'ADMIN') {
          toast.error('Access denied. Admin credentials required.');
          return;
        }

        setAdminAuth(data.data.user, data.data.token);
        toast.success('Welcome back, Admin!');
        router.push('/admin');
      } else if (data.requires_mfa_setup) {
        // Admin MFA setup required
        localStorage.setItem('admin_temp_token', data.data?.token || '');
        localStorage.setItem('admin_setup_email', email);
        toast('MFA setup required for admin account');
        router.push('/admin/mfa-setup');
      } else if (data.requires_mfa_code) {
        // Admin MFA code required
        setShowMfaInput(true);
        toast('Enter your admin MFA code or recovery code');
      } else {
        toast.error(data.message || 'Login failed');
      }
    } catch (error) {
      console.error('Login error:', error);
      toast.error('Login failed. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 flex items-center justify-center p-4">
      <Card className="w-full max-w-md border-purple-500/20 bg-slate-900/50 backdrop-blur-xl">
        <CardHeader className="text-center space-y-2">
          <div className="mx-auto h-16 w-16 bg-purple-500/10 rounded-full flex items-center justify-center">
            <Shield className="h-8 w-8 text-purple-400" />
          </div>
          <CardTitle className="text-2xl font-bold text-white">
            {showMfaInput 
              ? (useRecoveryCode ? 'Use Recovery Code' : 'Admin MFA Verification')
              : 'Admin Portal'}
          </CardTitle>
          <p className="text-gray-400 text-sm">T-Trade Administration</p>
        </CardHeader>

        <CardContent>
          <form onSubmit={handleLogin} className="space-y-4">
            {!showMfaInput ? (
              <>
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className="w-full pl-10 pr-4 py-3 bg-slate-800 border border-slate-700 rounded-lg focus:ring-2 focus:ring-purple-500 text-white"
                      placeholder="admin@t-trade.com"
                      required
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="w-full pl-10 pr-4 py-3 bg-slate-800 border border-slate-700 rounded-lg focus:ring-2 focus:ring-purple-500 text-white"
                      placeholder="••••••••"
                      required
                    />
                  </div>
                </div>
              </>
            ) : (
              <>
                {!useRecoveryCode ? (
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Authenticator Code</label>
                    <p className="text-sm text-gray-400 mb-4">Enter the 6-digit code from your authenticator app</p>
                    <input
                      type="text"
                      value={mfaCode}
                      onChange={(e) => setMfaCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                      className="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-center text-2xl tracking-widest font-mono text-white"
                      placeholder="000000"
                      maxLength={6}
                      autoFocus
                      required
                    />
                  </div>
                ) : (
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Recovery Code</label>
                    <p className="text-sm text-gray-400 mb-4">Enter one of your backup recovery codes</p>
                    <input
                      type="text"
                      value={recoveryCode}
                      onChange={(e) => setRecoveryCode(e.target.value.toUpperCase().replace(/[^A-F0-9]/g, '').slice(0, 16))}
                      className="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-center text-xl tracking-wider font-mono text-white"
                      placeholder="XXXXXXXXXXXXXXXX"
                      maxLength={16}
                      autoFocus
                      required
                    />
                  </div>
                )}

                <div className="flex items-center justify-between text-sm">
                  <button
                    type="button"
                    onClick={() => {
                      setUseRecoveryCode(!useRecoveryCode);
                      setMfaCode('');
                      setRecoveryCode('');
                    }}
                    className="text-purple-400 hover:text-purple-300 flex items-center space-x-1"
                  >
                    <Key className="h-4 w-4" />
                    <span>{useRecoveryCode ? 'Use authenticator code' : 'Use recovery code'}</span>
                  </button>
                  
                  <button
                    type="button"
                    onClick={() => {
                      setShowMfaInput(false);
                      setUseRecoveryCode(false);
                      setMfaCode('');
                      setRecoveryCode('');
                    }}
                    className="text-gray-400 hover:text-gray-300"
                  >
                    ← Back to login
                  </button>
                </div>
              </>
            )}

            <button
              type="submit"
              disabled={isLoading || (showMfaInput && !useRecoveryCode && mfaCode.length !== 6) || (showMfaInput && useRecoveryCode && recoveryCode.length < 8)}
              className="w-full py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:from-purple-700 hover:to-blue-700 font-semibold disabled:opacity-50 flex items-center justify-center space-x-2"
            >
              {isLoading ? (
                <>
                  <Spinner size="sm" />
                  <span>{showMfaInput ? 'Verifying...' : 'Authenticating...'}</span>
                </>
              ) : (
                <>
                  <Shield className="h-5 w-5" />
                  <span>{showMfaInput ? 'Verify & Sign In' : 'Sign In to Admin'}</span>
                </>
              )}
            </button>
          </form>

          <div className="mt-6 p-3 bg-red-500/10 border border-red-500/20 rounded-lg">
            <p className="text-xs text-red-400 text-center">🔒 Authorized Personnel Only</p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
