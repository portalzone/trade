'use client';

import { useEffect, useState } from 'react';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { 
  FileCheck, 
  Building2, 
  Clock,
  CheckCircle,
  XCircle,
  AlertCircle,
  Download,
  Eye
} from 'lucide-react';
import toast from 'react-hot-toast';

interface BusinessVerification {
  id: number;
  user_id: number;
  business_name: string;
  registration_number: string;
  business_type: string;
  business_address: string;
  verification_status: string;
  submitted_at: string;
  cac_certificate_url?: string;
  tin_certificate_url?: string;
  user?: {
    id: number;
    full_name: string;
    email: string;
    phone: string;
  };
}

export default function KYCApprovalPage() {
  const { adminToken } = useAdminAuthStore();
  const [verifications, setVerifications] = useState<BusinessVerification[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedVerification, setSelectedVerification] = useState<BusinessVerification | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [rejectionReason, setRejectionReason] = useState('');

  useEffect(() => {
    fetchVerifications();
  }, []);

  const fetchVerifications = async () => {
    setIsLoading(true);
    try {
      const response = await fetch('http://localhost:8000/api/admin/business/verifications?pending=true', {
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();

      if (data.success) {
        setVerifications(data.data.data || []);
      }
    } catch (error) {
      console.error('Error fetching verifications:', error);
      toast.error('Failed to load verifications');
    } finally {
      setIsLoading(false);
    }
  };

  const handleApprove = async (id: number) => {
    if (!confirm('Are you sure you want to approve this business verification?')) {
      return;
    }

    setActionLoading(true);
    try {
      const response = await fetch(`http://localhost:8000/api/admin/business/verifications/${id}/approve`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Business verification approved! User upgraded to Tier 2.');
        setShowModal(false);
        fetchVerifications();
      } else {
        toast.error(data.message || 'Failed to approve verification');
      }
    } catch (error) {
      console.error('Error approving verification:', error);
      toast.error('Failed to approve verification');
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async (id: number) => {
    if (!rejectionReason.trim()) {
      toast.error('Please provide a rejection reason');
      return;
    }

    setActionLoading(true);
    try {
      const response = await fetch(`http://localhost:8000/api/admin/business/verifications/${id}/reject`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${adminToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ reason: rejectionReason }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Business verification rejected');
        setShowModal(false);
        setRejectionReason('');
        fetchVerifications();
      } else {
        toast.error(data.message || 'Failed to reject verification');
      }
    } catch (error) {
      console.error('Error rejecting verification:', error);
      toast.error('Failed to reject verification');
    } finally {
      setActionLoading(false);
    }
  };

  const openModal = (verification: BusinessVerification) => {
    setSelectedVerification(verification);
    setShowModal(true);
  };

  const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
      case 'pending':
        return <Badge variant="warning">Pending</Badge>;
      case 'under_review':
        return <Badge variant="default">Under Review</Badge>;
      case 'verified':
        return <Badge variant="success">Verified</Badge>;
      case 'rejected':
        return <Badge variant="destructive">Rejected</Badge>;
      default:
        return <Badge>{status}</Badge>;
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
          <h1 className="text-3xl font-bold text-gray-900">KYC Approvals</h1>
          <p className="text-gray-600 mt-1">Review and approve business verification submissions</p>
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
            <p className="text-gray-600">All business verification applications have been reviewed</p>
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
                      <Building2 className="h-6 w-6 text-blue-600" />
                      <h3 className="text-xl font-semibold text-gray-900">
                        {verification.business_name}
                      </h3>
                      {getStatusBadge(verification.verification_status)}
                    </div>

                    <div className="grid grid-cols-2 gap-4 mb-4">
                      <div>
                        <p className="text-sm text-gray-600">Registration Number</p>
                        <p className="font-medium text-gray-900">{verification.registration_number}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Business Type</p>
                        <p className="font-medium text-gray-900 capitalize">
                          {verification.business_type?.replace('_', ' ')}
                        </p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Owner</p>
                        <p className="font-medium text-gray-900">{verification.user?.full_name}</p>
                        <p className="text-sm text-gray-600">{verification.user?.email}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Submitted</p>
                        <p className="font-medium text-gray-900">
                          {new Date(verification.submitted_at).toLocaleDateString()}
                        </p>
                      </div>
                    </div>

                    <div className="mb-4">
                      <p className="text-sm text-gray-600">Business Address</p>
                      <p className="text-gray-900">{verification.business_address}</p>
                    </div>
                  </div>

                  <button
                    onClick={() => openModal(verification)}
                    className="ml-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center space-x-2"
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
          <div className="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-gray-200">
              <h2 className="text-2xl font-bold text-gray-900">Review Business Verification</h2>
              <p className="text-gray-600 mt-1">{selectedVerification.business_name}</p>
            </div>

            <div className="p-6 space-y-6">
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-3">Business Information</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Business Name</p>
                    <p className="font-medium text-gray-900">{selectedVerification.business_name}</p>
                  </div>
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Registration Number</p>
                    <p className="font-medium text-gray-900">{selectedVerification.registration_number}</p>
                  </div>
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Business Type</p>
                    <p className="font-medium text-gray-900 capitalize">
                      {selectedVerification.business_type?.replace('_', ' ')}
                    </p>
                  </div>
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Submitted</p>
                    <p className="font-medium text-gray-900">
                      {new Date(selectedVerification.submitted_at).toLocaleString()}
                    </p>
                  </div>
                </div>
              </div>

              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-3">Owner Information</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Full Name</p>
                    <p className="font-medium text-gray-900">{selectedVerification.user?.full_name}</p>
                  </div>
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">Email</p>
                    <p className="font-medium text-gray-900">{selectedVerification.user?.email}</p>
                  </div>
                </div>
              </div>

              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-3">Documents</h3>
                <div className="space-y-3">
                  {selectedVerification.cac_certificate_url && (
                    
                      < a href={selectedVerification.cac_certificate_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center justify-between p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition"
                    >
                      <div className="flex items-center space-x-3">
                        <FileCheck className="h-5 w-5 text-blue-600" />
                        <span className="font-medium text-blue-900">CAC Certificate</span>
                      </div>
                      <Download className="h-5 w-5 text-blue-600" />
                    </a>
                  )}

                  {selectedVerification.tin_certificate_url && (
                    
                      <a href={selectedVerification.tin_certificate_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center justify-between p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition"
                    >
                      <div className="flex items-center space-x-3">
                        <FileCheck className="h-5 w-5 text-purple-600" />
                        <span className="font-medium text-purple-900">TIN Certificate</span>
                      </div>
                      <Download className="h-5 w-5 text-purple-600" />
                    </a>
                  )}
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Rejection Reason (if rejecting)
                </label>
                <textarea
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                  rows={4}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                  placeholder="Provide detailed reason for rejection..."
                />
              </div>
            </div>

            <div className="p-6 border-t border-gray-200 flex items-center justify-end space-x-4">
              <button
                onClick={() => {
                  setShowModal(false);
                  setRejectionReason('');
                }}
                className="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold"
                disabled={actionLoading}
              >
                Cancel
              </button>
              <button
                onClick={() => handleReject(selectedVerification.id)}
                disabled={actionLoading || !rejectionReason.trim()}
                className="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
              >
                {actionLoading ? (
                  <>
                    <Spinner size="sm" />
                    <span>Processing...</span>
                  </>
                ) : (
                  <>
                    <XCircle className="h-4 w-4" />
                    <span>Reject</span>
                  </>
                )}
              </button>
              <button
                onClick={() => handleApprove(selectedVerification.id)}
                disabled={actionLoading}
                className="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
              >
                {actionLoading ? (
                  <>
                    <Spinner size="sm" />
                    <span>Processing...</span>
                  </>
                ) : (
                  <>
                    <CheckCircle className="h-4 w-4" />
                    <span>Approve & Upgrade to Tier 2</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}