import api from './api';
import { LoginCredentials, RegisterData, AuthResponse, ApiResponse } from '@/types';

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const { data } = await api.post<ApiResponse<AuthResponse>>('/auth/login', credentials);
    if (!data.success || !data.data) {
      throw new Error(data.error || 'Login failed');
    }
    return data.data;
  },

  async register(userData: RegisterData): Promise<AuthResponse> {
    const { data } = await api.post<ApiResponse<AuthResponse>>('/auth/register', userData);
    if (!data.success || !data.data) {
      throw new Error(data.error || 'Registration failed');
    }
    return data.data;
  },

  async logout(): Promise<void> {
    await api.post('/auth/logout');
  },

  async getProfile(): Promise<AuthResponse> {
    const { data } = await api.get<ApiResponse<AuthResponse>>('/auth/profile');
    if (!data.success || !data.data) {
      throw new Error('Failed to fetch profile');
    }
    return data.data;
  },
};
