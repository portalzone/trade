import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface User {
  id: number;
  email: string;
  phone_number: string;
  full_name: string;
  username: string;
  user_type: string;
  kyc_status: string;
  kyc_tier: number;
  account_status: string;
}

interface Wallet {
  id: number;
  available_balance: string;
  locked_escrow_funds: string;
  total_balance: number;
  wallet_status: string;
}

interface AuthState {
  user: User | null;
  token: string | null;
  wallet: Wallet | null;
  setAuth: (user: User, token: string, wallet: Wallet) => void;
  updateWallet: (wallet: Wallet) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      wallet: null,
      setAuth: (user, token, wallet) => {
        set({ user, token, wallet });
      },
      updateWallet: (wallet) => {
        set({ wallet });
      },
      logout: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        set({ user: null, token: null, wallet: null });
      },
    }),
    {
      name: 'auth-storage',
    }
  )
);
