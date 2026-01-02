'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { appConfig } from '@/lib/config';
import { 
  Shield,
  CheckCircle,
  Clock,
  AlertCircle,
  Upload,
  ArrowRight
} from 'lucide-react';
import Link from 'next/link';
import toast from 'react-hot-toast';

export default function KYCUpgradePage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
    }
  }, [mounted, user, router]);

  if (!mounted || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  const currentTier = user.kyc_tier;
  const kycStatus = user.kyc_status;

  const tiers = [
    {
      tier: 1,
      name: 'Tier 1 - Casual Citizen',
      status: currentTier >= 1 ? 'completed' : 'locked',
      upgradeRoute: null,
      limits: {
        transaction: '₦100,000',
        daily: '₦200,000',
        monthly: '₦500,000',
      },
      requirements: [
        'Phone number verification',
        'Email verification',
        'NIN or BVN verification',
        'Basic profile information',
      ],
      features: [
        'Buy and sell products',
        'Payment link generation',
        'Basic escrow protection',
        'Standard withdrawal (3-5 days)',
      ],
    },
    {
      tier: 2,
      name: 'Tier 2 - Registered Business',
      status: currentTier >= 2 ? 'completed' : currentTier === 1 ? 'available' : 'locked',
      upgradeRoute: '/kyc/tier2',
      limits: {
        transaction: '₦5,000,000',
        daily: '₦10,000,000',
        monthly: '₦50,000,000',
      },
      requirements: [
        'All Tier 1 requirements',
        'CAC business registration certificate',
        'Director identification with liveness check',
        'Business bank account verification',
        'Beneficial owner disclosure',
        'Business address verification',
      ],
      features: [
        'All Tier 1 features',
        'Branded storefront (subdomain)',
        'Multi-product catalog',
        'Bulk product upload',
        'Customer reviews management',
        'Express withdrawal (24 hours)',
        'Priority customer support',
      ],
    },
    {
      tier: 3,
      name: 'Tier 3 - Enterprise',
      status: currentTier >= 3 ? 'completed' : currentTier === 2 ? 'available' : 'locked',
      upgradeRoute: null, // Coming soon
      limits: {
        transaction: 'Unlimited',
        daily: 'Unlimited',
        monthly: 'Custom limits',
      },
      requirements: [
        'All Tier 2 requirements',
        'Full KYB documentation',
        'UBO (Ultimate Beneficial Owner) disclosure',
        'AML sanctions screening clearance',
        'Enhanced Due Diligence (EDD)',
        'Premises inspection',
        'Legal counsel verification',
      ],
      features: [
        'All Tier 2 features',
        'White-label storefront (custom domain)',
        'Multi-user role management',
        'Custom escrow rules',
        'API access for integrations',
        'Dedicated account manager',
        'Advanced analytics & reporting',
        'Same-day withdrawal',
        'Priority dispute resolution',
      ],
    },
  ];

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="h-6 w-6 text-green-600" />;
      case 'available':
        return <Clock className="h-6 w-6 text-blue-600" />;
      default:
        return <AlertCircle className="h-6 w-6 text-gray-400" />;
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
        return <Badge variant="success">Verified</Badge>;
      case 'available':
        return <Badge variant="default">Available</Badge>;
      default:
        return <Badge variant="secondary">Locked</Badge>;
    }
  };

  const handleContactSupport = () => {
    toast(`📧 Email: ${appConfig.support.email}\n📞 Phone: ${appConfig.support.phone}`, {
      duration: 5000,
      icon: '💬',
    });
  };

  const handleStartVerification = (tier: number, route: string | null) => {
    if (route) {
      router.push(route);
    } else {
      toast(`🔒 Tier ${tier} upgrade coming soon! Contact ${appConfig.support.email} for early access.`, {
        duration: 4000,
        icon: '🚀',
      });
    }
  };

  const nextTier = tiers.find(t => t.tier === currentTier + 1);

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-6xl mx-auto space-y-6">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center space-x-3 mb-2">
            <Shield className="h-8 w-8 text-blue-600" />
            <h1 className="text-3xl font-bold text-gray-900">KYC Verification & Tier Upgrade</h1>
          </div>
          <p className="text-gray-600">
            Unlock higher transaction limits and premium features by upgrading your account tier
          </p>
        </div>

        {/* Current Status */}
        <Card className="border-l-4 border-l-blue-500">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 mb-1">Current Tier</p>
                <p className="text-2xl font-bold text-gray-900">
                  Tier {currentTier} - {tiers[currentTier - 1]?.name.split(' - ')[1]}
                </p>
                <div className="flex items-center space-x-2 mt-2">
                  <span className="text-sm text-gray-600">Status:</span>
                  <Badge variant="default">{kycStatus}</Badge>
                </div>
              </div>
              {nextTier && (
                <div className="text-right">
                  <p className="text-sm text-gray-600 mb-2">Next Tier Available</p>
                  <button
                    onClick={() => handleStartVerification(nextTier.tier, nextTier.upgradeRoute)}
                    className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center space-x-2"
                  >
                    <span>Upgrade to Tier {nextTier.tier}</span>
                    <ArrowRight className="h-4 w-4" />
                  </button>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Tier Cards */}
        <div className="space-y-6">
          {tiers.map((tierData) => (
            <Card
              key={tierData.tier}
              className={`${
                tierData.status === 'completed'
                  ? 'border-l-4 border-l-green-500'
                  : tierData.status === 'available'
                  ? 'border-l-4 border-l-blue-500'
                  : 'opacity-60'
              }`}
            >
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div className="flex items-center space-x-3">
                    {getStatusIcon(tierData.status)}
                    <div>
                      <CardTitle className="text-xl">{tierData.name}</CardTitle>
                      <p className="text-sm text-gray-600 mt-1">
                        {tierData.status === 'completed'
                          ? 'You have access to this tier'
                          : tierData.status === 'available'
                          ? 'Available for upgrade'
                          : 'Complete previous tier to unlock'}
                      </p>
                    </div>
                  </div>
                  {getStatusBadge(tierData.status)}
                </div>
              </CardHeader>

              <CardContent className="space-y-4">
                {/* Transaction Limits */}
                <div className="bg-gray-50 rounded-lg p-4">
                  <p className="font-semibold text-gray-900 mb-3">Transaction Limits</p>
                  <div className="grid grid-cols-3 gap-4 text-sm">
                    <div>
                      <p className="text-gray-600">Per Transaction</p>
                      <p className="font-semibold text-gray-900">{tierData.limits.transaction}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Daily Limit</p>
                      <p className="font-semibold text-gray-900">{tierData.limits.daily}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Monthly Limit</p>
                      <p className="font-semibold text-gray-900">{tierData.limits.monthly}</p>
                    </div>
                  </div>
                </div>

                {/* Requirements & Features */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Requirements */}
                  <div>
                    <p className="font-semibold text-gray-900 mb-2">Requirements</p>
                    <ul className="space-y-1">
                      {tierData.requirements.map((req, idx) => (
                        <li key={idx} className="text-sm text-gray-600 flex items-start space-x-2">
                          <span className="text-blue-600 mt-0.5">•</span>
                          <span>{req}</span>
                        </li>
                      ))}
                    </ul>
                  </div>

                  {/* Features */}
                  <div>
                    <p className="font-semibold text-gray-900 mb-2">Features</p>
                    <ul className="space-y-1">
                      {tierData.features.map((feature, idx) => (
                        <li key={idx} className="text-sm text-gray-600 flex items-start space-x-2">
                          <span className="text-green-600 mt-0.5">✓</span>
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>

                {/* Action Button */}
                {tierData.status === 'available' && (
                  <div className="pt-4 border-t border-gray-200">
                    {tierData.upgradeRoute ? (
                      <Link href={tierData.upgradeRoute}>
                        <button
                          className="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center space-x-2"
                        >
                          <Upload className="h-4 w-4" />
                          <span>Start Tier {tierData.tier} Verification</span>
                        </button>
                      </Link>
                    ) : (
                      <button
                        onClick={() => handleStartVerification(tierData.tier, tierData.upgradeRoute)}
                        className="w-full px-6 py-3 bg-gray-400 text-white rounded-lg cursor-not-allowed font-semibold flex items-center justify-center space-x-2"
                      >
                        <Clock className="h-4 w-4" />
                        <span>Tier {tierData.tier} Coming Soon</span>
                      </button>
                    )}
                  </div>
                )}

                {tierData.status === 'completed' && (
                  <div className="pt-4 border-t border-gray-200">
                    <div className="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center space-x-3">
                      <CheckCircle className="h-5 w-5 text-green-600" />
                      <p className="text-sm text-green-900 font-medium">
                        Tier {tierData.tier} verification completed
                      </p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Help Section */}
        <Card className="bg-blue-50 border-blue-200">
          <CardContent className="p-6">
            <div className="flex items-start space-x-4">
              <AlertCircle className="h-6 w-6 text-blue-600 flex-shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold text-blue-900 mb-2">Need Help?</p>
                <p className="text-sm text-blue-800 mb-3">
                  Our support team is ready to assist you with the verification process.
                  Tier upgrades typically take 2-5 business days to review.
                </p>
                <button 
                  onClick={handleContactSupport}
                  className="text-sm text-blue-600 hover:text-blue-700 font-semibold"
                >
                  Contact Support →
                </button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
