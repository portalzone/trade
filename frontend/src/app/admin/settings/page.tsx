'use client';

import { useEffect, useState } from 'react';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { 
  User, 
  Lock, 
  Shield,
  Key,
  Settings as SettingsIcon,
  Copy,
  RefreshCw,
  Download,
  X
} from 'lucide-react';
import toast from 'react-hot-toast';

export default function AdminSettingsPage() {
  const { adminUser, adminToken, isHydrated } = useAdminAuthStore();
  const [isLoading, setIsLoading] = useState(false);
  
  // Profile settings
  const [fullName, setFullName] = useState('');
  const [email, setEmail] = useState('');
  
  // Password change
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isChangingPassword, setIsChangingPassword] = useState(false);
  
  // MFA settings
  const [mfaEnabled, setMfaEnabled] = useState(false);
  const [showRecoveryCodes, setShowRecoveryCodes] = useState(false);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [isLoadingCodes, setIsLoadingCodes] = useState(false);

  useEffect(() => {
    if (isHydrated && adminUser) {
      setFullName(adminUser.full_name || '');
      setEmail(adminUser.email || '');
      setMfaEnabled(adminUser.mfa_enabled || false);
    }
  }, [isHydrated, adminUser]);

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    try {
      const response = await fetch('http://localhost:8000/api/user/profile', {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          full_name: fullName,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Profile updated successfully!');
      } else {
        toast.error(data.message || 'Failed to update profile');
      }
    } catch (error) {
      toast.error('Connection error');
    } finally {
      setIsLoading(false);
    }
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();

    if (newPassword !== confirmPassword) {
      toast.error('Passwords do not match');
      return;
    }

    if (newPassword.length < 8) {
      toast.error('Password must be at least 8 characters');
      return;
    }

    setIsChangingPassword(true);

    try {
      const response = await fetch('http://localhost:8000/api/user/change-password', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          current_password: currentPassword,
          new_password: newPassword,
          new_password_confirmation: confirmPassword,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Password changed successfully!');
        setCurrentPassword('');
        setNewPassword('');
        setConfirmPassword('');
      } else {
        toast.error(data.message || 'Failed to change password');
      }
    } catch (error) {
      toast.error('Connection error');
    } finally {
      setIsChangingPassword(false);
    }
  };

  const handleViewRecoveryCodes = async () => {
    setIsLoadingCodes(true);
    try {
      const response = await fetch('http://localhost:8000/api/mfa/recovery-codes', {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();

      if (data.success) {
        setRecoveryCodes(data.data.recovery_codes);
        setShowRecoveryCodes(true);
      } else {
        toast.error(data.message || 'Failed to load recovery codes');
      }
    } catch (error) {
      toast.error('Connection error');
    } finally {
      setIsLoadingCodes(false);
    }
  };

  const handleRegenerateRecoveryCodes = async () => {
    if (!confirm('Are you sure? This will invalidate your current recovery codes.')) {
      return;
    }

    setIsLoadingCodes(true);
    try {
      const response = await fetch('http://localhost:8000/api/mfa/recovery-codes/regenerate', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();

      if (data.success) {
        setRecoveryCodes(data.data.recovery_codes);
        setShowRecoveryCodes(true);
        toast.success('Recovery codes regenerated!');
      } else {
        toast.error(data.message || 'Failed to regenerate codes');
      }
    } catch (error) {
      toast.error('Connection error');
    } finally {
      setIsLoadingCodes(false);
    }
  };

  const handleCopyCode = (code: string) => {
    navigator.clipboard.writeText(code);
    toast.success('Code copied to clipboard!');
  };

  const handleCopyAllCodes = () => {
    const allCodes = recoveryCodes.join('\n');
    navigator.clipboard.writeText(allCodes);
    toast.success('All codes copied to clipboard!');
  };

  const handleDownloadCodes = () => {
    const content = `T-Trade MFA Recovery Codes\n\nGenerated: ${new Date().toLocaleString()}\nAccount: ${email}\n\n${recoveryCodes.join('\n')}\n\n⚠️ Keep these codes safe and secure!\n⚠️ Each code can only be used once.`;
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `t-trade-recovery-codes-${Date.now()}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    toast.success('Recovery codes downloaded!');
  };

  const handleDisableMFA = async () => {
    const password = prompt('Enter your password to disable MFA:');
    
    if (!password) {
      return;
    }

    if (!confirm('Are you sure you want to disable MFA? This will make your account less secure.')) {
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch('http://localhost:8000/api/mfa/disable', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ password }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success('MFA disabled successfully!');
        setMfaEnabled(false);
        setShowRecoveryCodes(false);
        setRecoveryCodes([]);
        // Refresh page to update admin user state
        setTimeout(() => window.location.reload(), 1000);
      } else {
        toast.error(data.message || 'Failed to disable MFA');
      }
    } catch (error) {
      toast.error('Connection error');
    } finally {
      setIsLoading(false);
    }
  };

  if (!isHydrated) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Admin Settings</h1>
        <p className="text-gray-600 mt-1">Manage your admin account settings</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Profile Settings */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <User className="h-5 w-5" />
              <span>Profile Information</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleUpdateProfile} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Full Name
                </label>
                <input
                  type="text"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Email Address
                </label>
                <input
                  type="email"
                  value={email}
                  disabled
                  className="w-full px-4 py-2 border rounded-lg bg-gray-100 cursor-not-allowed"
                />
                <p className="text-xs text-gray-500 mt-1">Email cannot be changed</p>
              </div>

              <button
                type="submit"
                disabled={isLoading}
                className="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 font-semibold"
              >
                {isLoading ? 'Updating...' : 'Update Profile'}
              </button>
            </form>
          </CardContent>
        </Card>

        {/* Password Change */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <Lock className="h-5 w-5" />
              <span>Change Password</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleChangePassword} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Current Password
                </label>
                <input
                  type="password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  New Password
                </label>
                <input
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                  required
                  minLength={8}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Confirm New Password
                </label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                  required
                  minLength={8}
                />
              </div>

              <button
                type="submit"
                disabled={isChangingPassword}
                className="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 font-semibold"
              >
                {isChangingPassword ? 'Changing...' : 'Change Password'}
              </button>
            </form>
          </CardContent>
        </Card>

        {/* MFA Settings */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <Shield className="h-5 w-5" />
              <span>Multi-Factor Authentication</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
              <div>
                <p className="font-semibold text-gray-900">MFA Status</p>
                <p className="text-sm text-gray-600">
                  {mfaEnabled ? 'Enabled' : 'Disabled'}
                </p>
              </div>
              <div className={`px-4 py-2 rounded-full font-semibold ${mfaEnabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                {mfaEnabled ? 'Active' : 'Inactive'}
              </div>
            </div>

            {mfaEnabled && (
              <>
                <button
                  onClick={handleViewRecoveryCodes}
                  disabled={isLoadingCodes}
                  className="w-full flex items-center justify-center space-x-2 bg-white border-2 border-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-50 font-semibold disabled:opacity-50"
                >
                  <Key className="h-5 w-5" />
                  <span>{isLoadingCodes ? 'Loading...' : 'View Recovery Codes'}</span>
                </button>

                <button
                  onClick={handleRegenerateRecoveryCodes}
                  disabled={isLoadingCodes}
                  className="w-full flex items-center justify-center space-x-2 bg-white border-2 border-blue-300 text-blue-700 py-2 rounded-lg hover:bg-blue-50 font-semibold disabled:opacity-50"
                >
                  <RefreshCw className="h-5 w-5" />
                  <span>{isLoadingCodes ? 'Generating...' : 'Regenerate Recovery Codes'}</span>
                </button>

                <button
                  onClick={handleDisableMFA}
                  disabled={isLoading}
                  className="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 font-semibold disabled:opacity-50"
                >
                  {isLoading ? 'Disabling...' : 'Disable MFA'}
                </button>
              </>
            )}

            {!mfaEnabled && (
              <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p className="text-sm text-yellow-800">
                  ⚠️ MFA is not enabled. Enable it during your next login for enhanced security.
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* System Information */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <SettingsIcon className="h-5 w-5" />
              <span>System Information</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex justify-between py-2 border-b">
              <span className="text-gray-600">User ID</span>
              <span className="font-semibold">{adminUser?.id}</span>
            </div>
            <div className="flex justify-between py-2 border-b">
              <span className="text-gray-600">Username</span>
              <span className="font-semibold">@{adminUser?.username}</span>
            </div>
            <div className="flex justify-between py-2 border-b">
              <span className="text-gray-600">Account Type</span>
              <span className="font-semibold text-red-600">ADMIN</span>
            </div>
            <div className="flex justify-between py-2 border-b">
              <span className="text-gray-600">KYC Tier</span>
              <span className="font-semibold">Tier {adminUser?.kyc_tier}</span>
            </div>
            <div className="flex justify-between py-2">
              <span className="text-gray-600">Account Status</span>
              <span className="px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                {adminUser?.account_status}
              </span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Recovery Codes Modal */}
      {showRecoveryCodes && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-2xl font-bold">Recovery Codes</h2>
                <button
                  onClick={() => setShowRecoveryCodes(false)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <X className="h-6 w-6" />
                </button>
              </div>

              <div className="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p className="text-sm text-yellow-800">
                  ⚠️ <strong>Important:</strong> Save these codes in a secure location. Each code can only be used once to access your account if you lose your authenticator device.
                </p>
              </div>

              <div className="grid grid-cols-2 gap-3 mb-6">
                {recoveryCodes.map((code, index) => (
                  <div
                    key={index}
                    className="flex items-center justify-between p-3 bg-gray-50 border rounded-lg font-mono text-sm"
                  >
                    <span className="font-semibold">{code}</span>
                    <button
                      onClick={() => handleCopyCode(code)}
                      className="text-blue-600 hover:text-blue-700"
                    >
                      <Copy className="h-4 w-4" />
                    </button>
                  </div>
                ))}
              </div>

              <div className="flex gap-3">
                <button
                  onClick={handleCopyAllCodes}
                  className="flex-1 flex items-center justify-center space-x-2 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 font-semibold"
                >
                  <Copy className="h-5 w-5" />
                  <span>Copy All</span>
                </button>
                <button
                  onClick={handleDownloadCodes}
                  className="flex-1 flex items-center justify-center space-x-2 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 font-semibold"
                >
                  <Download className="h-5 w-5" />
                  <span>Download</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
