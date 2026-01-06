import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface AdminUser {
  id: number;
  email: string;
  full_name: string;
  username: string;
  user_type: string;
  kyc_tier: number;
  kyc_status: string;
  account_status: string;
  mfa_enabled: boolean;
  email_verified_at: string | null;
  phone_verified_at: string | null;
}

interface AdminAuthState {
  adminUser: AdminUser | null;
  adminToken: string | null;
  isHydrated: boolean;
  setAdminAuth: (user: AdminUser, token: string) => void;
  clearAdminAuth: () => void;
  setHydrated: (hydrated: boolean) => void;
  isAdminAuthenticated: () => boolean;
}

export const useAdminAuthStore = create<AdminAuthState>()(
  persist(
    (set, get) => ({
      adminUser: null,
      adminToken: null,
      isHydrated: false,
      setAdminAuth: (user, token) => set({ adminUser: user, adminToken: token }),
      clearAdminAuth: () => set({ adminUser: null, adminToken: null }),
      setHydrated: (hydrated) => set({ isHydrated: hydrated }),
      isAdminAuthenticated: () => {
        const state = get();
        return !!state.adminToken && !!state.adminUser;
      },
    }),
    {
      name: 'admin-auth-storage',
      onRehydrateStorage: () => (state) => {
        state?.setHydrated(true);
      },
    }
  )
);
