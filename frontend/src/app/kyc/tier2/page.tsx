'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  ArrowLeft,
  Building2,
  Upload,
  CheckCircle,
  AlertCircle,
  FileText,
  Clock,
  XCircle
} from 'lucide-react';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface VerificationData {
  id?: number;
  verification_status?: string;
  status?: string;
  rejection_reason?: string;
  created_at?: string;
  updated_at?: string;
  business_name?: string;
  tier?: string;
}

export default function Tier2UpgradePage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const [mounted, setMounted] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [verificationData, setVerificationData] = useState<VerificationData | null>(null);

  // Form state
  const [businessName, setBusinessName] = useState('');
  const [registrationNumber, setRegistrationNumber] = useState('');
  const [businessType, setBusinessType] = useState('');
  const [businessAddress, setBusinessAddress] = useState('');
  const [businessPhone, setBusinessPhone] = useState('');
  const [businessEmail, setBusinessEmail] = useState('');
  const [cacCertificate, setCacCertificate] = useState<File | null>(null);
  const [tinCertificate, setTinCertificate] = useState<File | null>(null);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted && !user) {
      router.push('/login');
      return;
    }
    if (mounted && user && user.kyc_tier >= 2) {
      toast.error('You already have Tier 2 access!');
      router.push('/kyc');
      return;
    }
    if (mounted && user) {
      checkVerificationStatus();
    }
  }, [mounted, user]);

  const checkVerificationStatus = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch('http://localhost:8000/api/business/verification/status', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      
      console.log('Full verification response:', data);
      console.log('Verification data object:', data.data);

      if (data.success && data.data) {
        // Check if it has an ID (means there's an actual submission)
        if (data.data.id) {
          setVerificationData(data.data);
        }
      }
    } catch (error) {
      console.error('Error checking verification status:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleFileChange = (
    e: React.ChangeEvent<HTMLInputElement>,
    setter: (file: File | null) => void
  ) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        toast.error('File size must be less than 5MB');
        return;
      }
      const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
      if (!allowedTypes.includes(file.type)) {
        toast.error('Only PDF, JPG, and PNG files are allowed');
        return;
      }
      setter(file);
      toast.success(`${file.name} uploaded successfully`);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!cacCertificate) {
      toast.error('Please upload CAC certificate');
      return;
    }

    if (!businessType) {
      toast.error('Please select business type');
      return;
    }

    setIsSubmitting(true);

    try {
      const token = localStorage.getItem('auth_token');
      const formData = new FormData();
      
      formData.append('business_name', businessName);
      formData.append('registration_number', registrationNumber);
      formData.append('business_type', businessType);
      formData.append('business_address', businessAddress);
      formData.append('business_phone', businessPhone);
      formData.append('business_email', businessEmail);
      formData.append('cac_certificate', cacCertificate);
      
      if (tinCertificate) {
        formData.append('tin_certificate', tinCertificate);
      }

      const response = await fetch('http://localhost:8000/api/business/verify/tier2', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Tier 2 verification submitted successfully! We will review your application within 2-5 business days.');
        router.push('/kyc');
      } else {
        if (data.errors) {
          const errorMessages = Object.entries(data.errors)
            .map(([field, messages]) => `${field}: ${(messages as string[]).join(', ')}`)
            .join('\n');
          toast.error(`Validation errors:\n${errorMessages}`);
        } else {
          toast.error(data.message || data.error || 'Failed to submit verification');
        }
      }
    } catch (error) {
      console.error('Tier 2 verification error:', error);
      toast.error('Failed to submit verification. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!mounted || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  // Get the actual status field (try both common names)
  const actualStatus = verificationData?.verification_status || verificationData?.status;

  // PENDING STATUS - Show review message
  if (verificationData && (actualStatus === 'PENDING' || actualStatus === 'pending')) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
        <div className="max-w-3xl mx-auto space-y-6">
          <Link href="/kyc">
            <button className="mb-4 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
              <ArrowLeft className="h-5 w-5" />
              <span>Back to KYC Overview</span>
            </button>
          </Link>

          <Card className="border-l-4 border-l-yellow-500">
            <CardContent className="p-8">
              <div className="flex flex-col items-center text-center space-y-4">
                <Clock className="h-16 w-16 text-yellow-600" />
                <h2 className="text-2xl font-bold text-gray-900">Application Under Review</h2>
                <Badge variant="warning" className="text-base px-4 py-1">PENDING</Badge>
                <p className="text-gray-600 max-w-md">
                  Your Tier 2 verification application has been submitted and is currently being reviewed by our team.
                </p>
                {verificationData.business_name && (
                  <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 w-full">
                    <p className="text-sm text-gray-700">
                      <strong>Business Name:</strong> {verificationData.business_name}
                    </p>
                  </div>
                )}
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 w-full">
                  <p className="text-sm text-yellow-900">
                    <strong>Submitted:</strong> {verificationData.created_at ? new Date(verificationData.created_at).toLocaleDateString() : 'Recently'}
                  </p>
                  <p className="text-sm text-yellow-900 mt-2">
                    <strong>Estimated Review Time:</strong> 2-5 business days
                  </p>
                </div>
                <p className="text-sm text-gray-500 mt-4">
                  You will receive an email notification once your application has been reviewed.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  // REJECTED STATUS - Allow resubmission
  if (verificationData && (actualStatus === 'REJECTED' || actualStatus === 'rejected')) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
        <div className="max-w-3xl mx-auto space-y-6">
          <Link href="/kyc">
            <button className="mb-4 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
              <ArrowLeft className="h-5 w-5" />
              <span>Back to KYC Overview</span>
            </button>
          </Link>

          <Card className="border-l-4 border-l-red-500 mb-6">
            <CardContent className="p-6">
              <div className="flex items-start space-x-4">
                <XCircle className="h-6 w-6 text-red-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h3 className="font-semibold text-red-900 mb-2">Application Rejected</h3>
                  <p className="text-sm text-red-800 mb-3">
                    {verificationData.rejection_reason || 'Your application did not meet our verification requirements.'}
                  </p>
                  <p className="text-sm text-red-700">
                    Please review the feedback above and contact support to resubmit.
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  // HAS VERIFICATION but APPROVED - this shouldn't happen as user would be Tier 2 already
  // So if we reach here with verificationData, it must be PENDING (show pending view just in case)
  if (verificationData && verificationData.id) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
        <div className="max-w-3xl mx-auto space-y-6">
          <Link href="/kyc">
            <button className="mb-4 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
              <ArrowLeft className="h-5 w-5" />
              <span>Back to KYC Overview</span>
            </button>
          </Link>

          <Card className="border-l-4 border-l-yellow-500">
            <CardContent className="p-8">
              <div className="flex flex-col items-center text-center space-y-4">
                <Clock className="h-16 w-16 text-yellow-600" />
                <h2 className="text-2xl font-bold text-gray-900">Verification In Progress</h2>
                <Badge variant="warning" className="text-base px-4 py-1">{actualStatus || 'PENDING'}</Badge>
                <p className="text-gray-600 max-w-md">
                  You have already submitted a Tier 2 verification application.
                </p>
                {verificationData.business_name && (
                  <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 w-full">
                    <p className="text-sm text-gray-700">
                      <strong>Business Name:</strong> {verificationData.business_name}
                    </p>
                    <p className="text-sm text-gray-700 mt-1">
                      <strong>Registration Number:</strong> {verificationData.registration_number || 'N/A'}
                    </p>
                  </div>
                )}
                <p className="text-sm text-gray-500 mt-4">
                  Please wait for our team to review your application, or contact support for more information.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  // NO SUBMISSION YET - Show form
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-8 px-4">
      <div className="max-w-3xl mx-auto space-y-6">
        {/* Header */}
        <div className="mb-8">
          <Link href="/kyc">
            <button className="mb-4 flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition">
              <ArrowLeft className="h-5 w-5" />
              <span>Back to KYC Overview</span>
            </button>
          </Link>

          <div className="flex items-center space-x-3 mb-2">
            <Building2 className="h-8 w-8 text-blue-600" />
            <h1 className="text-3xl font-bold text-gray-900">Tier 2 Verification</h1>
          </div>
          <p className="text-gray-600">
            Registered Business - Unlock ₦5M transaction limits
          </p>
        </div>

        {/* Info Card */}
        <Card className="bg-blue-50 border-blue-200">
          <CardContent className="p-6">
            <div className="flex items-start space-x-4">
              <AlertCircle className="h-6 w-6 text-blue-600 flex-shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold text-blue-900 mb-2">Before You Start</p>
                <ul className="text-sm text-blue-800 space-y-1">
                  <li>• Ensure all documents are clear and readable (PDF, JPG, or PNG)</li>
                  <li>• Maximum file size: 5MB per document</li>
                  <li>• Processing time: 2-5 business days</li>
                  <li>• All information must match your CAC registration</li>
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Form */}
        <Card>
          <CardHeader>
            <CardTitle>Business Information</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Business Name */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Business Name *
                </label>
                <input
                  type="text"
                  value={businessName}
                  onChange={(e) => setBusinessName(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Enter your registered business name"
                  required
                />
              </div>

              {/* Business Type */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Business Type *
                </label>
                <select
                  value={businessType}
                  onChange={(e) => setBusinessType(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  required
                >
                  <option value="">Select business type</option>
                  <option value="sole_proprietorship">Sole Proprietorship</option>
                  <option value="limited_liability">Limited Liability Company (LLC)</option>
                  <option value="partnership">Partnership</option>
                  <option value="enterprise">Enterprise/Corporation</option>
                </select>
              </div>

              {/* Registration Number */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  CAC Registration Number *
                </label>
                <input
                  type="text"
                  value={registrationNumber}
                  onChange={(e) => setRegistrationNumber(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="RC1234567"
                  required
                />
              </div>

              {/* Business Address */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Business Address *
                </label>
                <textarea
                  value={businessAddress}
                  onChange={(e) => setBusinessAddress(e.target.value)}
                  rows={3}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Enter your registered business address"
                  required
                />
              </div>

              {/* Business Phone */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Business Phone
                </label>
                <input
                  type="tel"
                  value={businessPhone}
                  onChange={(e) => setBusinessPhone(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="+234 800 000 0000"
                />
              </div>

              {/* Business Email */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Business Email
                </label>
                <input
                  type="email"
                  value={businessEmail}
                  onChange={(e) => setBusinessEmail(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="info@yourbusiness.com"
                />
              </div>

              {/* Document Uploads */}
              <div className="space-y-4">
                <h3 className="font-semibold text-gray-900">Required Documents</h3>

                {/* CAC Certificate */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    CAC Registration Certificate * (PDF, JPG, PNG - Max 5MB)
                  </label>
                  <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-blue-500 transition">
                    <input
                      type="file"
                      onChange={(e) => handleFileChange(e, setCacCertificate)}
                      accept=".pdf,.jpg,.jpeg,.png"
                      className="hidden"
                      id="cac-certificate"
                      required
                    />
                    <label
                      htmlFor="cac-certificate"
                      className="flex flex-col items-center cursor-pointer"
                    >
                      <Upload className="h-10 w-10 text-gray-400 mb-2" />
                      <p className="text-sm text-gray-600">
                        {cacCertificate ? (
                          <span className="flex items-center space-x-2 text-green-600">
                            <CheckCircle className="h-4 w-4" />
                            <span>{cacCertificate.name}</span>
                          </span>
                        ) : (
                          'Click to upload CAC certificate'
                        )}
                      </p>
                    </label>
                  </div>
                </div>

                {/* TIN Certificate (Optional) */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    TIN Certificate (Optional)
                  </label>
                  <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-blue-500 transition">
                    <input
                      type="file"
                      onChange={(e) => handleFileChange(e, setTinCertificate)}
                      accept=".pdf,.jpg,.jpeg,.png"
                      className="hidden"
                      id="tin-certificate"
                    />
                    <label
                      htmlFor="tin-certificate"
                      className="flex flex-col items-center cursor-pointer"
                    >
                      <Upload className="h-10 w-10 text-gray-400 mb-2" />
                      <p className="text-sm text-gray-600">
                        {tinCertificate ? (
                          <span className="flex items-center space-x-2 text-green-600">
                            <CheckCircle className="h-4 w-4" />
                            <span>{tinCertificate.name}</span>
                          </span>
                        ) : (
                          'Click to upload TIN certificate (optional)'
                        )}
                      </p>
                    </label>
                  </div>
                </div>
              </div>

              {/* Submit Button */}
              <div className="flex space-x-4">
                <Link href="/kyc" className="flex-1">
                  <button
                    type="button"
                    className="w-full px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold"
                  >
                    Cancel
                  </button>
                </Link>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                >
                  {isSubmitting ? (
                    <>
                      <Spinner size="sm" />
                      <span>Submitting...</span>
                    </>
                  ) : (
                    <>
                      <FileText className="h-4 w-4" />
                      <span>Submit for Review</span>
                    </>
                  )}
                </button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
