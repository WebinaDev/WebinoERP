import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig({
  esbuild: { jsx: 'automatic' },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./vitest.setup.ts'],
    // @webina/ui ships extensionless ESM imports; let Vite resolve them.
    server: { deps: { inline: ['@webina/ui'] } },
    include: ['**/*.{test,spec}.{ts,tsx}'],
    exclude: ['e2e/**', 'node_modules/**', '.next/**'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json-summary'],
      thresholds: {
        lines: 5,
        functions: 5,
        branches: 5,
        statements: 5,
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@/i18n': path.resolve(__dirname, './i18n.ts'),
    },
  },
});
