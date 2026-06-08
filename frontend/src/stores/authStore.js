import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import api from '../services/api';

export const useAuthStore = create(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      isAuthenticated: false,
      adminToken: null,
      adminUser: null,
      isImpersonating: false,

      login: async (email, password) => {
        const { data } = await api.post('/auth/login', { email, password });
        set({
          user: data.user,
          token: data.token.access_token,
          isAuthenticated: true,
          adminToken: null,
          adminUser: null,
          isImpersonating: false,
        });
        api.defaults.headers.common['Authorization'] = `Bearer ${data.token.access_token}`;
        return data;
      },

      register: async (name, email, password) => {
        const { data } = await api.post('/auth/register', { name, email, password });
        set({
          user: data.user,
          token: data.token.access_token,
          isAuthenticated: true,
          adminToken: null,
          adminUser: null,
          isImpersonating: false,
        });
        api.defaults.headers.common['Authorization'] = `Bearer ${data.token.access_token}`;
        return data;
      },

      impersonate: async (userId) => {
        const current = get();
        const { data } = await api.post(`/admin/users/${userId}/impersonate`);
        set({
          adminToken: current.token,
          adminUser: current.user,
          user: data.user,
          token: data.token.access_token,
          isImpersonating: true,
          isAuthenticated: true,
        });
        api.defaults.headers.common['Authorization'] = `Bearer ${data.token.access_token}`;
        return data;
      },

      stopImpersonating: () => {
        const { adminToken, adminUser } = get();
        if (!adminToken || !adminUser) return;
        set({
          user: adminUser,
          token: adminToken,
          adminToken: null,
          adminUser: null,
          isImpersonating: false,
          isAuthenticated: true,
        });
        api.defaults.headers.common['Authorization'] = `Bearer ${adminToken}`;
      },

      logout: () => {
        set({
          user: null,
          token: null,
          isAuthenticated: false,
          adminToken: null,
          adminUser: null,
          isImpersonating: false,
        });
        delete api.defaults.headers.common['Authorization'];
      },

      initAuth: () => {
        const token = get().token;
        if (token) {
          api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }
      },
    }),
    {
      name: 'autothreads-auth',
      partialize: (state) => ({
        user: state.user,
        token: state.token,
        isAuthenticated: state.isAuthenticated,
        adminToken: state.adminToken,
        adminUser: state.adminUser,
        isImpersonating: state.isImpersonating,
      }),
    }
  )
);
