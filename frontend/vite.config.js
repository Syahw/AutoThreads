import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 3000,
    host: true,
    // Leading dot = any ngrok subdomain (avoids updating when tunnel URL changes)
    allowedHosts: ['.ngrok-free.dev', '.ngrok-free.app'],
    proxy: {
      // /api/v1/... -> http://localhost/autothreads/backend/public/api/v1/...
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => `/autothreads/backend/public${path}`,
      },
      // Hook images — Meta and the UI fetch /media/{filename} over this ngrok tunnel
      '/media': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => `/autothreads/backend/public${path}`,
      },
    },
  },

});
