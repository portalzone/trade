'use client';

import { useEffect, useState } from 'react';
import { useAuthStore } from '@/store/authStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  Shield, 
  Building2, 
  Users, 
  FileText,
  CheckCircle,
  AlertCircle,
  Upload,
  ArrowLeft
} from 'lucide-react';
import { useRouter } from 'next/navigation';
import toast from 'react-hot-toast';

interface UBO {
  full_name: string;
  date_of_birth: string;
  nationality: string;
  ownership_percentage: number;
  id_type: string;
  id_number: string;
}

export default function Tier3Page() {
  const router = useRouter();
  const { user, token, isHydrated } = useAuthStore();
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [existingVerification, setExistingVerification] = useState<any>(null);

  // Form state
  const [annualRevenue, setAnnualRevenue] = useState('');
  const [transactionVolume, setTransactionVolume] = useState('');
  const [sourceOfFunds, setSourceOfFunds] = useState('');
  const [businessPurpose, setBusinessPurpose] = useState('');
  const [ubos, setUbos] = useState<UBO[]>([{
    full_name: '',
    date_of_birth: '',
    nationality: '',
    ownership_percentage: 0,
    id_type: 'passport',
    id_number: ''
  }]);
  const [financialStatements, setFinancialStatements] = useState<File | null>(null);
  const [bankStatements, setBankStatements] = useState<File | null>(null);

  useEffect(() => {
    if (isHydrated) {
      checkExistingVerification();
    }
  }, [isHydrated]);

  const getToken = () => {
    // Fallback: use token from auth store, or directly from localStorage
    return token || localStorage.getItem('auth_token') || '';
  };

  const checkExistingVerification = async () => {
    setIsLoading(true);
    const authToken = getToken();
    
    if (!authToken) {
      setIsLoading(false);
      return;
    }

    try {
      const response = await fetch('http://localhost:8000/api/tier3-verification/status', {
        headers: {
          'Authorization': `Bearer ${authToken}`,
          'Content-Type': 'application/json',
        },
      });
      
      if (response.ok) {
        const data = await response.json();
        if (data.success && data.data && data.data.status !== 'not_submitted') {
          setExistingVerification(data.data);
        }
      }
    } catch (error) {
      console.log('No existing verification found');
    } finally {
      setIsLoading(false);
    }
  };

  const addUBO = () => {
    if (ubos.length < 5) {
      setUbos([...ubos, {
        full_name: '',
        date_of_birth: '',
        nationality: '',
        ownership_percentage: 0,
        id_type: 'passport',
        id_number: ''
      }]);
    }
  };

  const removeUBO = (index: number) => {
    if (ubos.length > 1) {
      setUbos(ubos.filter((_, i) => i !== index));
    }
  };

  const updateUBO = (index: number, field: keyof UBO, value: any) => {
    const updated = [...ubos];
    updated[index] = { ...updated[index], [field]: value };
    setUbos(updated);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!annualRevenue || !transactionVolume || !sourceOfFunds || !businessPurpose) {
      toast.error('Please fill in all required fields');
      return;
    }

    if (!financialStatements || !bankStatements) {
      toast.error('Please upload all required documents');
      return;
    }

    const totalOwnership = ubos.reduce((sum, ubo) => sum + Number(ubo.ownership_percentage), 0);
    if (totalOwnership < 25) {
      toast.error('Total UBO ownership must be at least 25%');
      return;
    }

    setIsSubmitting(true);

    try {
      const authToken = getToken();
      const formData = new FormData();
      formData.append('annual_revenue', annualRevenue);
      formData.append('transaction_volume', transactionVolume);
      formData.append('source_of_funds', sourceOfFunds);
      formData.append('business_purpose', businessPurpose);
      formData.append('ubos', JSON.stringify(ubos));
      formData.append('financial_statements', financialStatements);
      formData.append('bank_statements', bankStatements);

      const response = await fetch('http://localhost:8000/api/tier3-verification', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${authToken}`,
        },
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Tier 3 verification submitted successfully! Our team will review your application.');
        router.push('/dashboard');
      } else {
        toast.error(data.message || 'Submission failed');
      }
    } catch (error) {
      console.error('Submission error:', error);
      toast.error('Failed to submit verification. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isHydrated || isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Spinner size="lg" />
      </div>
    );
  }

  if (existingVerification) {
    return (
      <div className="min-h-screen bg-gray-50 py-12 px-4">
        <div className="max-w-3xl mx-auto">
          <button
            onClick={() => router.back()}
            className="flex items-center text-gray-600 hover:text-gray-900 mb-6"
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back
          </button>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <Shield className="h-6 w-6 text-purple-600" />
                <span>Tier 3 Verification Status</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="text-center py-8">
                <div className="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mb-4">
                  <AlertCircle className="h-8 w-8 text-yellow-600" />
                </div>
                <h3 className="text-xl font-semibold mb-2">Verification Under Review</h3>
                <p className="text-gray-600">
                  Your Tier 3 verification is currently being reviewed by our compliance team.
                  We'll notify you once the review is complete.
                </p>
                <div className="mt-6">
                  <Badge variant="warning" className="text-lg px-4 py-2">
                    {existingVerification.verification_status}
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-12 px-4">
      <div className="max-w-4xl mx-auto">
        <button
          onClick={() => router.back()}
          className="flex items-center text-gray-600 hover:text-gray-900 mb-6"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back
        </button>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <Shield className="h-6 w-6 text-purple-600" />
              <span>Tier 3 Verification - Enterprise Account</span>
            </CardTitle>
            <p className="text-gray-600 mt-2">
              Unlock unlimited transaction limits and premium features
            </p>
          </CardHeader>

          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-8">
              {/* Rest of the form - keeping it exactly as before */}
              <div>
                <h3 className="text-lg font-semibold mb-4 flex items-center">
                  <FileText className="h-5 w-5 mr-2 text-blue-600" />
                  Financial Information
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-2">Annual Revenue *</label>
                    <input
                      type="text"
                      value={annualRevenue}
                      onChange={(e) => setAnnualRevenue(e.target.value)}
                      className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                      placeholder="e.g., ₦50,000,000"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-2">Monthly Transaction Volume *</label>
                    <input
                      type="text"
                      value={transactionVolume}
                      onChange={(e) => setTransactionVolume(e.target.value)}
                      className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                      placeholder="e.g., ₦10,000,000"
                      required
                    />
                  </div>
                </div>
              </div>

              <div>
                <div>
                  <label className="block text-sm font-medium mb-2">Source of Funds *</label>
                  <textarea
                    value={sourceOfFunds}
                    onChange={(e) => setSourceOfFunds(e.target.value)}
                    rows={3}
                    className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                    placeholder="Describe the primary sources of your business funds..."
                    required
                  />
                </div>
                <div className="mt-4">
                  <label className="block text-sm font-medium mb-2">Business Purpose *</label>
                  <textarea
                    value={businessPurpose}
                    onChange={(e) => setBusinessPurpose(e.target.value)}
                    rows={3}
                    className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                    placeholder="Explain how you plan to use this platform..."
                    required
                  />
                </div>
              </div>

              <div>
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold flex items-center">
                    <Users className="h-5 w-5 mr-2 text-green-600" />
                    Ultimate Beneficial Owners (UBOs)
                  </h3>
                  <button
                    type="button"
                    onClick={addUBO}
                    disabled={ubos.length >= 5}
                    className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                  >
                    + Add UBO
                  </button>
                </div>
                <p className="text-sm text-gray-600 mb-4">
                  List all individuals who own 25% or more of the business
                </p>

                {ubos.map((ubo, index) => (
                  <div key={index} className="p-4 border rounded-lg mb-4 bg-gray-50">
                    <div className="flex items-center justify-between mb-3">
                      <h4 className="font-semibold">UBO #{index + 1}</h4>
                      {ubos.length > 1 && (
                        <button
                          type="button"
                          onClick={() => removeUBO(index)}
                          className="text-red-600 hover:text-red-700 text-sm"
                        >
                          Remove
                        </button>
                      )}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium mb-2">Full Name *</label>
                        <input
                          type="text"
                          value={ubo.full_name}
                          onChange={(e) => updateUBO(index, 'full_name', e.target.value)}
                          className="w-full px-4 py-2 border rounded-lg"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2">Date of Birth *</label>
                        <input
                          type="date"
                          value={ubo.date_of_birth}
                          onChange={(e) => updateUBO(index, 'date_of_birth', e.target.value)}
                          className="w-full px-4 py-2 border rounded-lg"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2">Nationality *</label>
                        <input
                          type="text"
                          value={ubo.nationality}
                          onChange={(e) => updateUBO(index, 'nationality', e.target.value)}
                          className="w-full px-4 py-2 border rounded-lg"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2">Ownership % *</label>
                        <input
                          type="number"
                          min="25"
                          max="100"
                          value={ubo.ownership_percentage}
                          onChange={(e) => updateUBO(index, 'ownership_percentage', parseFloat(e.target.value))}
                          className="w-full px-4 py-2 border rounded-lg"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2">ID Type *</label>
                        <select
                          value={ubo.id_type}
                          onChange={(e) => updateUBO(index, 'id_type', e.target.value)}
                          className="w-full px-4 py-2 border rounded-lg"
                          required
                        >
                          <option value="passport">Passport</option>
                          <option value="national_id">National ID</option>
                          <option value="drivers_license">Driver's License</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2">ID Number *</label>
                        <input
                          type="text"
                          value={ubo.id_number}
                          onChange={(e) => updateUBO(index, 'id_number', e.target.value)}
                          className="w-full px-4 py-2 border rounded-lg"
                          required
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              <div>
                <h3 className="text-lg font-semibold mb-4 flex items-center">
                  <Upload className="h-5 w-5 mr-2 text-orange-600" />
                  Required Documents
                </h3>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium mb-2">Financial Statements (Last 12 months) *</label>
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={(e) => setFinancialStatements(e.target.files?.[0] || null)}
                      className="w-full px-4 py-2 border rounded-lg"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-2">Bank Statements (Last 6 months) *</label>
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={(e) => setBankStatements(e.target.files?.[0] || null)}
                      className="w-full px-4 py-2 border rounded-lg"
                      required
                    />
                  </div>
                </div>
              </div>

              <div className="pt-6">
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:from-purple-700 hover:to-blue-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                >
                  {isSubmitting ? (
                    <>
                      <Spinner size="sm" />
                      <span>Submitting...</span>
                    </>
                  ) : (
                    <>
                      <CheckCircle className="h-5 w-5" />
                      <span>Submit Tier 3 Verification</span>
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
