export interface User {
  id: number;
  email: string;
  phone_number: string;
  full_name: string;
  username: string;
  user_type: 'BUYER' | 'SELLER' | 'ADMIN';
  kyc_status: string;
  kyc_tier: number;
  account_status: string;
}

export interface Wallet {
  id: number;
  user_id: number;
  available_balance: string;
  locked_escrow_funds: string;
  total_balance: number;
  currency: string;
  wallet_status: string;
}

export interface Order {
  id: number;
  seller_id: number;
  buyer_id: number | null;
  title: string;
  description: string;
  price: string;
  currency: string;
  category: string | null;
  images: string | null;
  order_status: 'ACTIVE' | 'IN_ESCROW' | 'COMPLETED' | 'CANCELLED' | 'DISPUTED';
  escrow_locked_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  created_at: string;
  updated_at: string;
  seller?: User;
  buyer?: User;
  escrow_lock?: EscrowLock;
}

export interface EscrowLock {
  id: number;
  order_id: number;
  wallet_id: number;
  amount: string;
  platform_fee: string;
  lock_type: string;
  locked_at: string;
  released_at: string | null;
  refunded_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Dispute {
  id: number;
  order_id: number;
  raised_by_user_id: number;
  dispute_reason: string;
  dispute_status: 'OPEN' | 'UNDER_REVIEW' | 'RESOLVED_BUYER' | 'RESOLVED_SELLER' | 'RESOLVED_REFUND';
  admin_notes: string | null;
  resolution_details: string | null;
  resolved_at: string | null;
  created_at: string;
  updated_at: string;
  order?: Order;
  raised_by?: User;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterData {
  email: string;
  password: string;
  password_confirmation: string;
  full_name: string;
  username: string;
  phone_number: string;
  user_type: 'BUYER' | 'SELLER';
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data?: T;
  error?: string;
  errors?: Record<string, string[]>;
}

export interface AuthResponse {
  user: User;
  wallet: Wallet;
  token: string;
}
