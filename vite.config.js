import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'path'

export default defineConfig({
  plugins: [
    tailwindcss(),
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'vendor/kungfufafa/mekaya-theme/resources/css/theme.css',
        'vendor/kungfufafa/mekaya-theme/resources/js/mekaya.js',
      ],
      refresh: true,
    }),
    VitePWA({
      registerType: 'autoUpdate',
      injectRegister: 'auto',
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
        cleanupOutdatedCaches: true,
        sourcemap: true
      },
      manifest: {
        name: 'SAM - Sales Assistant Mobile',
        short_name: 'SAM',
        description: 'Sales Assistant Mobile - Backend & Admin Panel',
        theme_color: '#d97706',
        background_color: '#d97706',
        display: 'standalone',
        start_url: '/admin',
        scope: '/',
        icons: [
          {
            src: '/images/icons/icon-192x192.png',
            sizes: '192x192',
            type: 'image/png'
          },
          {
            src: '/images/icons/icon-512x512.png',
            sizes: '512x512',
            type: 'image/png'
          }
        ]
      }
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
})
