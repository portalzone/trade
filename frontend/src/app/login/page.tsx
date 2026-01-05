'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Mail, Lock, Shield } from 'lucide-react';
import toast from 'react-hot-toast';

export default function LoginPage() {
  const router = useRouter();
  const { login } = useAuthStore();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [mfaCode, setMfaCode] = useState('');
  const [showMfaInput, setShowMfaInput] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    try {
      const body: any = { email, password };
      if (showMfaInput && mfaCode) {
        body.mfa_code = mfaCode;
      }

      const response = await fetch('http://localhost:8000/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      const data = await response.json();

      if (data.success) {
        login(data.data.user, data.data.token, data.data.wallet);
        toast.success('Login successful!');
        router.push('/dashboard');
      } else if (data.requires_mfa_setup) {
        // MFA setup required
        localStorage.setItem('temp_token', data.data?.token || '');
        localStorage.setItem('setup_email', email);
        toast('MFA setup required for Tier 3');
        router.push('/mfa/setup');
      } else if (data.requires_mfa_code) {
        // MFA code required
        setShowMfaInput(true);
        toast('Enter your 6-digit MFA code');
      } else {
        toast.error(data.message || 'Login failed');
      }
    } catch (error) {
      console.error('Login error:', error);
      toast.error('Login failed');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-50 to-blue-50 flex items-center justify-center p-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-2xl text-center flex items-center justify-center space-x-2">
            {showMfaInput && <Shield className="h-6 w-6 text-purple-600" />}
            <span>{showMfaInput ? 'Two-Factor Authentication' : 'Login to T-Trade'}</span>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleLogin} className="space-y-4">
            {!showMfaInput ? (
              <>
                <div>
                  <label className="block text-sm font-medium mb-2">Email</label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                      placeholder="your@email.com"
                      required
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2">Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                      placeholder="••••••••"
                      required
                    />
                  </div>
                </div>
              </>
            ) : (
              <div>
                <label className="block text-sm font-medium mb-2">Authenticator Code</label>
                <p className="text-sm text-gray-600 mb-4">
                  Enter the 6-digit code from your authenticator app
                </p>
                <input
                  type="text"
                  value={mfaCode}
                  onChange={(e) => setMfaCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  className="w-full px-4 py-3 border rounded-lg text-center text-2xl tracking-widest font-mono focus:ring-2 focus:ring-purple-500"
                  placeholder="000000"
                  maxLength={6}
                  autoFocus
                  required
                />
                <button
                  type="button"
                  onClick={() => {
                    setShowMfaInput(false);
                    setMfaCode('');
                  }}
                  className="mt-4 text-sm text-purple-600 hover:text-purple-700"
                >
                  ← Back to login
                </button>
              </div>
            )}
            <button
              type="submit"
              disabled={isLoading || (showMfaInput && mfaCode.length !== 6)}
              className="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 font-semibold flex items-center justify-center space-x-2"
            >
              {isLoading ? (
                <>
                  <Spinner size="sm" />
                  <span>{showMfaInput ? 'Verifying...' : 'Logging in...'}</span>
                </>
              ) : (
                <span>{showMfaInput ? 'Verify & Login' : 'Login'}</span>
              )}
            </button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
