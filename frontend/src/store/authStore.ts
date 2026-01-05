import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface User {
  id: number;
  email: string;
  full_name: string;
  username: string;
  phone_number: string;
  user_type: 'BUYER' | 'SELLER';
  kyc_status: string;
  kyc_tier: number;
  account_status: string;
  email_verified_at: string | null;
  phone_verified_at: string | null;
  verification_tier: string | null;
}

interface Wallet {
  id: number;
  available_balance: number;
  locked_escrow_funds: number;
  total_balance: number;
  wallet_status: string;
  currency: string;
}

interface AuthState {
  user: User | null;
  token: string | null;
  wallet: Wallet | null;
  isHydrated: boolean;
  setHydrated: () => void;
  login: (user: User, token: string, wallet: Wallet) => void;
  setUser: (user: User) => void;
  setWallet: (wallet: Wallet) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      wallet: null,
      isHydrated: false,
      setHydrated: () => set({ isHydrated: true }),
      login: (user, token, wallet) => {
        set({ user, token, wallet });
        localStorage.setItem('auth_token', token);
      },
      setUser: (user) => set({ user }),
      setWallet: (wallet) => set({ wallet }),
      logout: () => {
        set({ user: null, token: null, wallet: null });
        localStorage.removeItem('auth_token');
      },
    }),
    {
      name: 'auth-storage',
      onRehydrateStorage: () => (state) => {
        state?.setHydrated();
      },
    }
  )
);
