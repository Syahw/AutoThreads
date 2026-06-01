import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 3000,
    proxy: {
      // Proxy API calls to the WAMP/Apache backend.
      // /api/v1/... -> http://localhost/AutoThreads/backend/public/api/v1/...
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => `/AutoThreads/backend/public${path}`,
      },
    },
  },

});
