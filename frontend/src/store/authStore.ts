import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { User, Wallet } from '@/types';

interface AuthState {
  user: User | null;
  wallet: Wallet | null;
  token: string | null;
  isAuthenticated: boolean;
  
  setAuth: (user: User, wallet: Wallet, token: string) => void;
  updateWallet: (wallet: Wallet) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      wallet: null,
      token: null,
      isAuthenticated: false,

      setAuth: (user, wallet, token) => {
        localStorage.setItem('auth_token', token);
        set({ user, wallet, token, isAuthenticated: true });
      },

      updateWallet: (wallet) => {
        set({ wallet });
      },

      logout: () => {
        localStorage.removeItem('auth_token');
        set({ user: null, wallet: null, token: null, isAuthenticated: false });
      },
    }),
    {
      name: 'auth-storage',
    }
  )
);
