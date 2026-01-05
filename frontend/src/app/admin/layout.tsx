'use client';

import { useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { useAdminAuthStore } from '@/store/adminAuthStore';
import Link from 'next/link';
import { 
  Shield, 
  LayoutDashboard, 
  Users, 
  FileCheck, 
  AlertTriangle,
  Settings,
  LogOut,
  Menu
} from 'lucide-react';
import { useState } from 'react';

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { adminUser, clearAdminAuth, isAdminAuthenticated } = useAdminAuthStore();
  const [sidebarOpen, setSidebarOpen] = useState(true);

  useEffect(() => {
    // Allow access to login and MFA setup pages without authentication
    const publicAdminPaths = ['/admin/login', '/admin/mfa-setup'];
    
    if (!isAdminAuthenticated() && !publicAdminPaths.includes(pathname)) {
      router.push('/admin/login');
    }
  }, [isAdminAuthenticated, pathname, router]);

  const handleLogout = () => {
    clearAdminAuth();
    router.push('/admin/login');
  };

  // Don't show layout on login and MFA setup pages
  if (pathname === '/admin/login' || pathname === '/admin/mfa-setup') {
    return <>{children}</>;
  }

  const navigation = [
    { name: 'Dashboard', href: '/admin', icon: LayoutDashboard },
    { name: 'KYC Approvals', href: '/admin/kyc', icon: FileCheck },
    { name: 'Tier 3 Approvals', href: '/admin/tier3', icon: Shield },
    { name: 'Disputes', href: '/admin/disputes', icon: AlertTriangle },
    { name: 'Users', href: '/admin/users', icon: Users },
    { name: 'Settings', href: '/admin/settings', icon: Settings },
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top Navigation */}
      <nav className="bg-gradient-to-r from-slate-900 to-purple-900 text-white shadow-lg">
        <div className="px-4 py-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <button
                onClick={() => setSidebarOpen(!sidebarOpen)}
                className="p-2 rounded-lg hover:bg-white/10 transition"
              >
                <Menu className="h-6 w-6" />
              </button>
              <div className="flex items-center space-x-2">
                <Shield className="h-6 w-6 text-purple-400" />
                <span className="text-xl font-bold">T-Trade Admin</span>
              </div>
            </div>

            <div className="flex items-center space-x-4">
              <div className="text-right">
                <p className="text-sm font-semibold">{adminUser?.full_name}</p>
                <p className="text-xs text-purple-300">{adminUser?.email}</p>
              </div>
              <button
                onClick={handleLogout}
                className="p-2 rounded-lg hover:bg-white/10 transition"
                title="Logout"
              >
                <LogOut className="h-5 w-5" />
              </button>
            </div>
          </div>
        </div>
      </nav>

      <div className="flex">
        {/* Sidebar */}
        {sidebarOpen && (
          <aside className="w-64 bg-white shadow-lg min-h-screen">
            <nav className="p-4 space-y-2">
              {navigation.map((item) => {
                const isActive = pathname === item.href;
                return (
                  <Link
                    key={item.name}
                    href={item.href}
                    className={`flex items-center space-x-3 px-4 py-3 rounded-lg transition ${
                      isActive
                        ? 'bg-purple-100 text-purple-700 font-semibold'
                        : 'text-gray-700 hover:bg-gray-100'
                    }`}
                  >
                    <item.icon className="h-5 w-5" />
                    <span>{item.name}</span>
                  </Link>
                );
              })}
            </nav>
          </aside>
        )}

        {/* Main Content */}
        <main className="flex-1 p-6">
          {children}
        </main>
      </div>
    </div>
  );
}
