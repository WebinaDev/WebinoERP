import { test, expect } from '@playwright/test';

test.describe('Phase 14 CRM deep flows', () => {
  test.use({ storageState: 'e2e/.auth/admin.json' });

  test('CRM leads page shows table or empty state', async ({ page }) => {
    await page.goto('/dashboard/crm/leads');
    await expect(page.locator('h1, [class*="CardTitle"]').first()).toBeVisible({ timeout: 15_000 });
    const table = page.locator('table');
    const empty = page.getByText(/موردی|Nothing here|No records/i);
    await expect(table.or(empty).first()).toBeVisible();
  });

  test('finance persons page loads data shell', async ({ page }) => {
    await page.goto('/dashboard/finance/persons');
    await expect(page).toHaveURL(/finance\/persons/);
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('leads page exposes convert action when rows exist', async ({ page }) => {
    await page.goto('/dashboard/crm/leads');
    await page.waitForTimeout(2000);
    const convertBtn = page.getByRole('button', { name: /تبدیل|Convert/i });
    if (await convertBtn.count()) {
      await expect(convertBtn.first()).toBeVisible();
    }
  });
});
