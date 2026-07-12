import { test, expect } from '@playwright/test';

test.describe('Webino ERP smoke', () => {
  test('login page renders', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('dashboard redirects unauthenticated users', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/login/);
  });
});
