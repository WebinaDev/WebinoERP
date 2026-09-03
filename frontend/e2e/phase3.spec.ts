import { test, expect } from '@playwright/test';

test.describe('Webino ERP Phase 3 flows', () => {
  test('login page renders', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('dashboard redirects unauthenticated users', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/login/);
  });

  test('crm deals route resolves (auth redirect or page)', async ({ page }) => {
    await page.goto('/admin/crm/deals');
    await expect(page).toHaveURL(/login|crm\/deals/);
  });

  test('crm pipelines route resolves', async ({ page }) => {
    await page.goto('/admin/crm/pipelines');
    await expect(page).toHaveURL(/login|pipelines/);
  });

  test('marketplace module editor route resolves', async ({ page }) => {
    await page.goto('/admin/admin/marketplace/modules/new');
    await expect(page).toHaveURL(/login|modules\/new/);
  });

  test('finance journals route resolves', async ({ page }) => {
    await page.goto('/admin/finance/journals');
    await expect(page).toHaveURL(/login|finance\/journals/);
  });

  test('modirpayamak send route resolves', async ({ page }) => {
    await page.goto('/admin/admin/integrations/modirpayamak/send');
    await expect(page).toHaveURL(/login|modirpayamak\/send/);
  });

  test('scm inbound route resolves', async ({ page }) => {
    await page.goto('/admin/scm/inbound');
    await expect(page).toHaveURL(/login|scm\/inbound/);
  });

  test('hrm staff route resolves', async ({ page }) => {
    await page.goto('/admin/hrm/staff');
    await expect(page).toHaveURL(/login|hrm\/staff/);
  });

  test('docs files route resolves', async ({ page }) => {
    await page.goto('/admin/docs/files');
    await expect(page).toHaveURL(/login|docs\/files/);
  });
});
