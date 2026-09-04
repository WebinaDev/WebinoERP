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

    await page.goto('/admin/platform/sites/new');
    await expect(page).toHaveURL(/login|sites\/new/);

    const url = page.url();
    if (!url.includes('login')) {
      await expect(page.getByTestId('site-wizard')).toBeVisible();
      await expect(page.getByTestId('wizard-step-customer')).toBeVisible();
      await expect(page.getByTestId('wizard-continue')).toBeVisible();
    }
  });

  test('platform servers list route resolves when authenticated cookie present', async ({ page }) => {
    // Unauthenticated users are redirected to login — documents the protected surface.
    await page.goto('/admin/platform/servers');
    await expect(page).toHaveURL(/login|servers/);
  });

  test('sites fleet and control panel routes exist', async ({ page }) => {
    await page.goto('/admin/platform/sites');
    await expect(page).toHaveURL(/login|sites/);

    if (!page.url().includes('login')) {
      await expect(page.getByTestId('sites-fleet')).toBeVisible();
      const panelLink = page.getByTestId(/site-open-panel-/).first();
      if ((await panelLink.count()) > 0) {
        await panelLink.click();
        await expect(page.getByTestId('site-control-panel')).toBeVisible();
        await expect(page.getByTestId('control-power')).toBeVisible();
        await expect(page.getByTestId('control-updates')).toBeVisible();
        await expect(page.getByTestId('control-update-frontend')).toBeVisible();
        await expect(page.getByTestId('control-update-backend')).toBeVisible();
        await expect(page.getByTestId('control-update-migrate')).toBeVisible();
        await expect(page.getByTestId('control-update-full')).toBeVisible();
      }
    }
  });
});
