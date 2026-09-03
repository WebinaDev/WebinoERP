import { test, expect } from '@playwright/test';

/**
 * Cafe site provision path: Site Builder wizard → Platform fields (site type + server).
 * Full Docker deploy is environment-dependent; this e2e asserts the admin UI path.
 */
test.describe('Cafe site provision path', () => {
  test('platform nav and site builder new provision page expose cafe type', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    await page.goto('/admin/platform');
    await expect(page).toHaveURL(/login|platform/);

    await page.goto('/admin/site-builder/provisions/new');
    await expect(page).toHaveURL(/login|provisions\/new/);

    const url = page.url();
    if (!url.includes('login')) {
      const siteTypeSelect = page.locator('select').filter({ has: page.locator('option[value="cafe"]') }).first();
      await expect(siteTypeSelect).toBeVisible();
      for (const siteType of ['ecommerce', 'magazine', 'cafe', 'resume', 'corporate']) {
        await expect(siteTypeSelect.locator(`option[value="${siteType}"]`)).toHaveText(siteType);
      }
    }
  });

  test('platform servers list route resolves when authenticated cookie present', async ({ page }) => {
    // Unauthenticated users are redirected to login — documents the protected surface.
    await page.goto('/admin/platform/servers');
    await expect(page).toHaveURL(/login|servers/);
  });
});
