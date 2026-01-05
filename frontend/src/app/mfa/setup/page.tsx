'use client';

import { useEffect, useState } from 'react';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Shield, CheckCircle, Copy } from 'lucide-react';
import { useRouter } from 'next/navigation';
import toast from 'react-hot-toast';

export default function MfaSetupPage() {
  const router = useRouter();
  const token = typeof window !== 'undefined' ? (localStorage.getItem('temp_token') || useAuthStore().token) : '';
  const [isLoading, setIsLoading] = useState(true);
  const [qrCode, setQrCode] = useState('');
  const [secret, setSecret] = useState('');
  const [code, setCode] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [showRecovery, setShowRecovery] = useState(false);

  useEffect(() => {
    setupMfa();
  }, []);

  const setupMfa = async () => {
    setIsLoading(true);
    try {
      const response = await fetch('http://localhost:8000/api/mfa/setup', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      const data = await response.json();
      if (data.success) {
        setQrCode(data.data.qr_code_svg);
        setSecret(data.data.secret);
      } else {
        toast.error(data.message || 'Failed to setup MFA');
      }
    } catch (error) {
      toast.error('Failed to setup MFA');
    } finally {
      setIsLoading(false);
    }
  };

  const verifyCode = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsVerifying(true);
    try {
      const response = await fetch('http://localhost:8000/api/mfa/verify', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ code }),
      });
      const data = await response.json();
      if (data.success) {
        setRecoveryCodes(data.data.recovery_codes);
        setShowRecovery(true);
        toast.success('MFA enabled successfully!');
      } else {
        toast.error(data.message || 'Invalid code');
      }
    } catch (error) {
      toast.error('Verification failed');
    } finally {
      setIsVerifying(false);
    }
  };

  const copyRecoveryCodes = () => {
    navigator.clipboard.writeText(recoveryCodes.join('\n'));
    toast.success('Recovery codes copied!');
  };

  const finishSetup = () => {
    localStorage.removeItem('temp_token');
    router.push('/login');
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Spinner size="lg" />
      </div>
    );
  }

  if (showRecovery) {
    return (
      <div className="min-h-screen bg-gray-50 py-12 px-4">
        <div className="max-w-2xl mx-auto">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <CheckCircle className="h-6 w-6 text-green-600" />
                <span>MFA Enabled Successfully!</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              <div>
                <h3 className="text-lg font-semibold mb-3">Recovery Codes</h3>
                <p className="text-gray-600 mb-4">
                  Save these recovery codes in a safe place. You can use them to access your account if you lose your authenticator device.
                </p>
                <div className="bg-gray-100 p-4 rounded-lg font-mono text-sm space-y-2">
                  {recoveryCodes.map((code, idx) => (
                    <div key={idx}>{code}</div>
                  ))}
                </div>
                <button
                  onClick={copyRecoveryCodes}
                  className="mt-4 flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                  <Copy className="h-4 w-4" />
                  <span>Copy Codes</span>
                </button>
              </div>
              <button
                onClick={finishSetup}
                className="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold"
              >
                Continue to Login
              </button>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-12 px-4">
      <div className="max-w-2xl mx-auto">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <Shield className="h-6 w-6 text-purple-600" />
              <span>Set Up Two-Factor Authentication</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            <div>
              <h3 className="text-lg font-semibold mb-3">Step 1: Scan QR Code</h3>
              <p className="text-gray-600 mb-4">
                Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
              </p>
              <div className="flex justify-center bg-white p-4 rounded-lg border">
                <img 
                  src={`data:image/svg+xml;base64,${qrCode}`} 
                  alt="QR Code" 
                  className="w-48 h-48"
                />
              </div>
            </div>

            <div>
              <h3 className="text-lg font-semibold mb-3">Manual Entry</h3>
              <p className="text-gray-600 mb-2">Or enter this code manually:</p>
              <div className="bg-gray-100 p-3 rounded font-mono text-sm">
                {secret}
              </div>
            </div>

            <form onSubmit={verifyCode}>
              <div>
                <h3 className="text-lg font-semibold mb-3">Step 2: Verify Code</h3>
                <p className="text-gray-600 mb-4">
                  Enter the 6-digit code from your authenticator app
                </p>
                <input
                  type="text"
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  className="w-full px-4 py-3 border rounded-lg text-center text-2xl tracking-widest font-mono"
                  placeholder="000000"
                  maxLength={6}
                  required
                />
              </div>
              <button
                type="submit"
                disabled={isVerifying || code.length !== 6}
                className="mt-6 w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 font-semibold flex items-center justify-center space-x-2"
              >
                {isVerifying ? (
                  <>
                    <Spinner size="sm" />
                    <span>Verifying...</span>
                  </>
                ) : (
                  <span>Verify & Enable MFA</span>
                )}
              </button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}