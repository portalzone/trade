'use client';

import { useEffect, useState } from 'react';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { FileCheck, Building2, CheckCircle, XCircle, Download, Eye, Users } from 'lucide-react';
import toast from 'react-hot-toast';

interface Tier3Verification {
  id: number;
  user_id: number;
  annual_revenue: string;
  transaction_volume: string;
  source_of_funds: string;
  business_purpose: string;
  verification_status: string;
  submitted_at: string;
  financial_statements_url?: string;
  bank_statements_url?: string;
  user?: {
    id: number;
    full_name: string;
    email: string;
  };
  beneficial_owners?: Array<{
    id: number;
    full_name: string;
    date_of_birth: string;
    nationality: string;
    ownership_percentage: number;
    id_type: string;
    id_number: string;
  }>;
}

export default function AdminTier3Page() {
  const { adminToken, isHydrated } = useAdminAuthStore();
  const [verifications, setVerifications] = useState<Tier3Verification[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedVerification, setSelectedVerification] = useState<Tier3Verification | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [rejectionReason, setRejectionReason] = useState('');

useEffect(() => {
    if (isHydrated) {
      fetchVerifications();
    }
  }, [isHydrated]);

  const fetchVerifications = async () => {
    setIsLoading(true);
    try {
      const response = await fetch('http://localhost:8000/api/admin/tier3/verifications?pending=true', {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });
      const data = await response.json();
      console.log('Tier3 API Response:', data);
      if (data.success) {
        setVerifications(data.data || []);
      }
    } catch (error) {
      console.error('Error fetching verifications:', error);
      toast.error('Failed to load verifications');
    } finally {
      setIsLoading(false);
    }
  };

  const handleApprove = async (id: number) => {
    if (!confirm('Are you sure you want to approve this Tier 3 verification?')) return;
    setActionLoading(true);
    try {
      const response = await fetch(`http://localhost:8000/api/admin/tier3/verifications/${id}/approve`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      });
      const data = await response.json();
      if (data.success) {
        toast.success('Tier 3 verification approved! User upgraded to Enterprise.');
        setShowModal(false);
        fetchVerifications();
      } else {
        toast.error(data.message || 'Failed to approve verification');
      }
    } catch (error) {
      toast.error('Failed to approve verification');
    } finally {
      setActionLoading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Tier 3 (Enterprise) Approvals</h1>
          <p className="text-gray-600 mt-1">Review and approve enterprise verification submissions</p>
        </div>
        <div className="text-right">
          <p className="text-sm text-gray-600">Pending Applications</p>
          <p className="text-3xl font-bold text-purple-600">{verifications.length}</p>
        </div>
      </div>

      {verifications.length === 0 ? (
        <Card>
          <CardContent className="p-12 text-center">
            <FileCheck className="h-16 w-16 text-gray-300 mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-gray-900 mb-2">No Pending Verifications</h3>
            <p className="text-gray-600">All Tier 3 applications have been reviewed</p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {verifications.map((verification) => (
            <Card key={verification.id} className="hover:shadow-lg transition">
              <CardContent className="p-6">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center space-x-3 mb-3">
                      <Building2 className="h-6 w-6 text-purple-600" />
                      <h3 className="text-xl font-semibold text-gray-900">{verification.user?.full_name}</h3>
                      <Badge variant={verification.verification_status === 'pending' ? 'warning' : 'default'}>
                        {verification.verification_status}
                      </Badge>
                    </div>
                    <div className="grid grid-cols-2 gap-4 mb-4">
                      <div>
                        <p className="text-sm text-gray-600">Annual Revenue</p>
                        <p className="font-medium text-gray-900">₦{parseInt(verification.annual_revenue).toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Monthly Volume</p>
                        <p className="font-medium text-gray-900">₦{parseInt(verification.transaction_volume).toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">User Email</p>
                        <p className="font-medium text-gray-900">{verification.user?.email}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Submitted</p>
                        <p className="font-medium text-gray-900">{new Date(verification.submitted_at).toLocaleDateString()}</p>
                      </div>
                    </div>
                  </div>
                  <button
                    onClick={() => { setSelectedVerification(verification); setShowModal(true); }}
                    className="ml-4 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold flex items-center space-x-2"
                  >
                    <Eye className="h-4 w-4" />
                    <span>Review</span>
                  </button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {showModal && selectedVerification && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg max-w-5xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b">
              <h2 className="text-2xl font-bold">Review Tier 3 Verification</h2>
              <p className="text-gray-600 mt-1">{selectedVerification.user?.full_name}</p>
            </div>
            <div className="p-6 space-y-6">
              <div>
                <h3 className="text-lg font-semibold mb-3">Financial Information</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Annual Revenue</p>
                    <p className="font-medium">₦{parseInt(selectedVerification.annual_revenue).toLocaleString()}</p>
                  </div>
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Monthly Volume</p>
                    <p className="font-medium">₦{parseInt(selectedVerification.transaction_volume).toLocaleString()}</p>
                  </div>
                </div>
              </div>

              <div>
                <h3 className="text-lg font-semibold mb-3">Source of Funds</h3>
                <p className="text-gray-700 bg-gray-50 p-4 rounded-lg">{selectedVerification.source_of_funds}</p>
              </div>

              <div>
                <h3 className="text-lg font-semibold mb-3">Business Purpose</h3>
                <p className="text-gray-700 bg-gray-50 p-4 rounded-lg">{selectedVerification.business_purpose}</p>
              </div>

              {selectedVerification.beneficial_owners && selectedVerification.beneficial_owners.length > 0 && (
                <div>
                  <h3 className="text-lg font-semibold mb-3 flex items-center">
                    <Users className="h-5 w-5 mr-2 text-green-600" />
                    Ultimate Beneficial Owners
                  </h3>
                  {selectedVerification.beneficial_owners.map((ubo, idx) => (
                    <div key={ubo.id} className="mb-3 p-4 bg-gray-50 rounded-lg">
                      <h4 className="font-semibold mb-2">UBO #{idx + 1}</h4>
                      <div className="grid grid-cols-3 gap-3 text-sm">
                        <div>
                          <p className="text-gray-600">Name</p>
                          <p className="font-medium">{ubo.full_name}</p>
                        </div>
                        <div>
                          <p className="text-gray-600">Nationality</p>
                          <p className="font-medium">{ubo.nationality}</p>
                        </div>
                        <div>
                          <p className="text-gray-600">Ownership</p>
                          <p className="font-medium">{ubo.ownership_percentage}%</p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}

              <div>
                <h3 className="text-lg font-semibold mb-3">Documents</h3>
                <div className="space-y-3">
                  {selectedVerification.financial_statements_url && (
                    <a href={`http://localhost:8000${selectedVerification.financial_statements_url}`} target="_blank" rel="noopener noreferrer" className="flex items-center justify-between p-4 bg-blue-50 border rounded-lg hover:bg-blue-100 transition">
                      <div className="flex items-center space-x-3">
                        <FileCheck className="h-5 w-5 text-blue-600" />
                        <span className="font-medium">Financial Statements</span>
                      </div>
                      <Download className="h-5 w-5" />
                    </a>
                  )}
                  {selectedVerification.bank_statements_url && (
                    <a href={`http://localhost:8000${selectedVerification.bank_statements_url}`} target="_blank" rel="noopener noreferrer" className="flex items-center justify-between p-4 bg-purple-50 border rounded-lg hover:bg-purple-100 transition">
                      <div className="flex items-center space-x-3">
                        <FileCheck className="h-5 w-5 text-purple-600" />
                        <span className="font-medium">Bank Statements</span>
                      </div>
                      <Download className="h-5 w-5" />
                    </a>
                  )}
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Rejection Reason (if rejecting)</label>
                <textarea value={rejectionReason} onChange={(e) => setRejectionReason(e.target.value)} rows={4} className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Provide detailed reason for rejection..." />
              </div>
            </div>
            <div className="p-6 border-t flex justify-end space-x-4">
              <button onClick={() => { setShowModal(false); setRejectionReason(''); }} className="px-6 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
              <button onClick={() => handleApprove(selectedVerification.id)} disabled={actionLoading} className="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center space-x-2">
                {actionLoading ? <><Spinner size="sm" /><span>Processing...</span></> : <><CheckCircle className="h-4 w-4" /><span>Approve & Upgrade to Tier 3</span></>}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}