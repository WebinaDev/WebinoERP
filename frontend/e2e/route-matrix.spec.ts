import { test, expect } from '@playwright/test';
import manifest from './routes.manifest.json';

const authRoutes = manifest.routes.filter((row) => row.e2eAuth);

test.describe('Route matrix authenticated smoke', () => {
  test.describe.configure({ mode: 'parallel' });

  test.use({ storageState: 'e2e/.auth/admin.json' });

  for (const row of authRoutes) {
    const label = row.route || 'dashboard';
    const path = row.e2ePath ? `/dashboard/${row.e2ePath}` : '/dashboard';
    const urlPattern = row.route ? new RegExp(row.route.split('/')[0] || 'dashboard') : /dashboard/;

    test(`${label} loads with h1`, { tag: '@slow' }, async ({ page }) => {
      await page.goto(path);
      await expect(page).toHaveURL(urlPattern);
      await expect(page.locator('h1').first()).toBeVisible({ timeout: 15_000 });
      await expect(page.getByText(/مسیر ناشناخته|Unknown route/i)).toHaveCount(0);
    });
  }
});
