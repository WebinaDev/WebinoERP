import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Accessibility smoke', () => {
  test('login page has no critical axe violations', async ({ page }) => {
    await page.goto('/login');
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    const critical = results.violations.filter((v) => v.impact === 'critical' || v.impact === 'serious');
    expect(critical).toEqual([]);
  });
});

test.describe('Dashboard keyboard', () => {
  test.use({ storageState: 'e2e/.auth/admin.json' });

  test('dashboard shows main heading', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 15_000 });
  });
});
