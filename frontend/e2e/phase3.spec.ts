import { test, expect } from '@playwright/test';

test.describe('Webino ERP Phase 3 flows', () => {
  test('login page renders', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('dashboard redirects unauthenticated users', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/login/);
  });

  test('crm deals route resolves (auth redirect or page)', async ({ page }) => {
    await page.goto('/dashboard/crm/deals');
    await expect(page).toHaveURL(/login|crm\/deals/);
  });

  test('crm pipelines route resolves', async ({ page }) => {
    await page.goto('/dashboard/crm/pipelines');
    await expect(page).toHaveURL(/login|pipelines/);
  });

  test('marketplace module editor route resolves', async ({ page }) => {
    await page.goto('/dashboard/admin/marketplace/modules/new');
    await expect(page).toHaveURL(/login|modules\/new/);
  });

  test('finance journals route resolves', async ({ page }) => {
    await page.goto('/dashboard/finance/journals');
    await expect(page).toHaveURL(/login|finance\/journals/);
  });

  test('modirpayamak send route resolves', async ({ page }) => {
    await page.goto('/dashboard/admin/integrations/modirpayamak/send');
    await expect(page).toHaveURL(/login|modirpayamak\/send/);
  });

  test('scm inbound route resolves', async ({ page }) => {
    await page.goto('/dashboard/scm/inbound');
    await expect(page).toHaveURL(/login|scm\/inbound/);
  });

  test('hrm staff route resolves', async ({ page }) => {
    await page.goto('/dashboard/hrm/staff');
    await expect(page).toHaveURL(/login|hrm\/staff/);
  });

  test('docs files route resolves', async ({ page }) => {
    await page.goto('/dashboard/docs/files');
    await expect(page).toHaveURL(/login|docs\/files/);
  });
});
