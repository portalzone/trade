import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface AdminUser {
  id: number;
  full_name: string;
  email: string;
  user_type: string;
}

interface AdminAuthState {
  adminUser: AdminUser | null;
  adminToken: string | null;
  isHydrated: boolean;
  setHydrated: () => void;
  setAdminAuth: (user: AdminUser, token: string) => void;
  clearAdminAuth: () => void;
  isAdminAuthenticated: () => boolean;
}

export const useAdminAuthStore = create<AdminAuthState>()(
  persist(
    (set, get) => ({
      adminUser: null,
      adminToken: null,
      isHydrated: false,

      setHydrated: () => set({ isHydrated: true }),

      setAdminAuth: (user, token) => {
        set({ adminUser: user, adminToken: token });
        localStorage.setItem('admin_token', token);
      },

      clearAdminAuth: () => {
        set({ adminUser: null, adminToken: null });
        localStorage.removeItem('admin_token');
      },

      isAdminAuthenticated: () => {
        const state = get();
        return !!(state.adminUser && state.adminToken);
      },
    }),
    {
      name: 'admin-auth-storage',
      onRehydrateStorage: () => (state) => {
        state?.setHydrated();
      },
    }
  )
);
