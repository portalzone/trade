'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { 
  Shield, 
  CheckCircle, 
  XCircle, 
  Key, 
  AlertTriangle,
  RefreshCw,
  Monitor,
  Smartphone,
  Tablet
} from 'lucide-react';

interface ActivityLog {
  id: string;
  activity_type: string;
  ip_address: string;
  user_agent: string;
  device_type: string;
  details: any;
  created_at: string;
}

interface MfaActivityLogProps {
  token: string;
}

export default function MfaActivityLog({ token }: MfaActivityLogProps) {
  const [logs, setLogs] = useState<ActivityLog[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetchActivityLogs();
  }, []);

  const fetchActivityLogs = async () => {
    setIsLoading(true);
    try {
      const response = await fetch('http://localhost:8000/api/mfa/activity-logs', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const data = await response.json();
      if (data.success) {
        setLogs(data.data);
      }
    } catch (error) {
      console.error('Failed to fetch activity logs:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const getActivityIcon = (activityType: string) => {
    switch (activityType) {
      case 'setup':
        return <Shield className="h-5 w-5 text-blue-600" />;
      case 'verify_success':
      case 'login_success':
        return <CheckCircle className="h-5 w-5 text-green-600" />;
      case 'verify_failed':
      case 'login_failed':
        return <XCircle className="h-5 w-5 text-red-600" />;
      case 'recovery_used':
        return <Key className="h-5 w-5 text-orange-600" />;
      case 'disabled':
        return <AlertTriangle className="h-5 w-5 text-yellow-600" />;
      case 'codes_regenerated':
        return <RefreshCw className="h-5 w-5 text-purple-600" />;
      default:
        return <Shield className="h-5 w-5 text-gray-600" />;
    }
  };

  const getActivityLabel = (activityType: string) => {
    const labels: Record<string, string> = {
      setup: 'MFA Setup',
      verify_success: 'Verification Success',
      verify_failed: 'Verification Failed',
      recovery_used: 'Recovery Code Used',
      disabled: 'MFA Disabled',
      login_success: 'Login Success',
      login_failed: 'Login Failed',
      codes_regenerated: 'Recovery Codes Regenerated',
    };
    return labels[activityType] || 'Unknown Activity';
  };

  const getDeviceIcon = (deviceType: string) => {
    switch (deviceType) {
      case 'mobile':
        return <Smartphone className="h-4 w-4 text-gray-500" />;
      case 'tablet':
        return <Tablet className="h-4 w-4 text-gray-500" />;
      case 'desktop':
        return <Monitor className="h-4 w-4 text-gray-500" />;
      default:
        return <Monitor className="h-4 w-4 text-gray-500" />;
    }
  };

  const getActivityColor = (activityType: string) => {
    switch (activityType) {
      case 'verify_success':
      case 'login_success':
        return 'border-l-green-500 bg-green-50';
      case 'verify_failed':
      case 'login_failed':
        return 'border-l-red-500 bg-red-50';
      case 'disabled':
        return 'border-l-yellow-500 bg-yellow-50';
      case 'setup':
        return 'border-l-blue-500 bg-blue-50';
      case 'recovery_used':
        return 'border-l-orange-500 bg-orange-50';
      case 'codes_regenerated':
        return 'border-l-purple-500 bg-purple-50';
      default:
        return 'border-l-gray-500 bg-gray-50';
    }
  };

  if (isLoading) {
    return (
      <Card>
        <CardContent className="p-6">
          <div className="flex items-center justify-center">
            <Spinner size="lg" />
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center justify-between">
          <span>MFA Activity History</span>
          <button
            onClick={fetchActivityLogs}
            className="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1"
          >
            <RefreshCw className="h-4 w-4" />
            Refresh
          </button>
        </CardTitle>
      </CardHeader>
      <CardContent>
        {logs.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            <Shield className="h-12 w-12 mx-auto mb-3 opacity-50" />
            <p>No MFA activity recorded yet</p>
          </div>
        ) : (
          <div className="space-y-3 max-h-96 overflow-y-auto">
            {logs.map((log) => (
              <div
                key={log.id}
                className={`p-4 border-l-4 rounded-r-lg ${getActivityColor(log.activity_type)}`}
              >
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-3">
                    <div className="mt-0.5">
                      {getActivityIcon(log.activity_type)}
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">
                        {getActivityLabel(log.activity_type)}
                      </p>
                      <div className="flex items-center gap-4 mt-2 text-sm text-gray-600">
                        <div className="flex items-center gap-1">
                          {getDeviceIcon(log.device_type)}
                          <span className="capitalize">{log.device_type}</span>
                        </div>
                        <span>IP: {log.ip_address}</span>
                      </div>
                      {log.details && log.details.reason && (
                        <p className="text-sm text-gray-600 mt-1">
                          Reason: {log.details.reason}
                        </p>
                      )}
                    </div>
                  </div>
                  <div className="text-right text-xs text-gray-500">
                    <p>{new Date(log.created_at).toLocaleDateString()}</p>
                    <p>{new Date(log.created_at).toLocaleTimeString()}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
