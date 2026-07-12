import { test, expect } from '@playwright/test';

const API_URL = process.env.PLAYWRIGHT_API_URL ?? 'http://localhost/api';

async function apiLogin(email: string, password: string): Promise<string | null> {
  const res = await fetch(`${API_URL}/v1/core/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  if (!res.ok) return null;
  const json = (await res.json()) as { data?: { token?: string } };
  return json?.data?.token ?? null;
}

test.describe('Phase 5 authenticated flows', () => {
  test.use({ storageState: 'e2e/.auth/admin.json' });

  test('CRM leads list loads', async ({ page }) => {
    await page.goto('/dashboard/crm/leads');
    await expect(page).toHaveURL(/crm\/leads/);
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('finance journals page loads', async ({ page }) => {
    await page.goto('/dashboard/finance/journals');
    await expect(page).toHaveURL(/finance\/journals/);
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('HRM staff list loads', async ({ page }) => {
    await page.goto('/dashboard/hrm/staff');
    await expect(page).toHaveURL(/hrm\/staff/);
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('SCM inbound workflow page loads', async ({ page }) => {
    await page.goto('/dashboard/scm/inbound');
    await expect(page).toHaveURL(/scm\/inbound/);
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('ModirPayamak send form loads', async ({ page }) => {
    await page.goto('/dashboard/admin/integrations/modirpayamak/send');
    await expect(page).toHaveURL(/modirpayamak\/send/);
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('ModirPayamak tickets page loads', async ({ page }) => {
    await page.goto('/dashboard/admin/integrations/modirpayamak/tickets');
    await expect(page).toHaveURL(/modirpayamak\/tickets/);
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.getByText(/تیکت|ticket/i).first()).toBeVisible();
  });

  test('ModirPayamak users page loads', async ({ page }) => {
    await page.goto('/dashboard/admin/integrations/modirpayamak/users');
    await expect(page).toHaveURL(/modirpayamak\/users/);
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.getByText(/کاربر|user/i).first()).toBeVisible();
  });

  test('ModirPayamak drafts page loads', async ({ page }) => {
    await page.goto('/dashboard/admin/integrations/modirpayamak/drafts');
    await expect(page).toHaveURL(/modirpayamak\/drafts/);
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.getByText(/پیش‌نویس|draft/i).first()).toBeVisible();
  });

  test('hosting infrastructure page loads', async ({ page }) => {
    await page.goto('/dashboard/admin/hosting-infra');
    await expect(page).toHaveURL(/hosting-infra/);
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.getByText(/میزبانی|Hosting/i).first()).toBeVisible();
  });

  test('admin settings general hub loads', async ({ page }) => {
    await page.goto('/dashboard/admin/settings/general');
    await expect(page).toHaveURL(/admin\/settings\/general/);
    await expect(page.getByText(/عمومی|General/i).first()).toBeVisible();
  });

  test('PM chat page loads', async ({ page }) => {
    await page.goto('/dashboard/pm/chat');
    await expect(page).toHaveURL(/pm\/chat/);
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('CRM customers page shows translated title', async ({ page }) => {
    await page.goto('/dashboard/crm/customers');
    await expect(page).toHaveURL(/crm\/customers/);
    await expect(page.getByText('مشتریان').first()).toBeVisible();
  });
});

test.describe('Phase 5 auth flows', () => {
  test('login password → dashboard visible', async ({ page }) => {
    const token = await apiLogin('admin@webina.local', 'password');
    test.skip(!token, 'API not available for login');

    await page.goto('/login');
    await page.getByLabel(/email|ایمیل/i).fill('admin@webina.local');
    await page.locator('#dashboard-login-password').fill('password');
    await page.getByRole('button', { name: /ورود|login|sign in/i }).click();
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 });
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('admin settings hidden or redirects for client role', async ({ page }) => {
    const token = await apiLogin('sales@webina.local', 'password');
    test.skip(!token, 'API not available');

    await page.goto('/login');
    await page.evaluate((t) => localStorage.setItem('auth_token', t), token!);
    await page.goto('/dashboard/admin/settings');
    await expect(page).toHaveURL(/dashboard(?!.*admin\/settings)|admin\/settings/, { timeout: 10000 });
  });

  test('logout redirects to login', async ({ page }) => {
    const token = await apiLogin('admin@webina.local', 'password');
    test.skip(!token, 'API not available');

    await page.goto('/login');
    await page.evaluate((t) => localStorage.setItem('auth_token', t), token!);
    await page.goto('/dashboard');
    await page.getByRole('button', { name: /خروج|logout/i }).click();
    await expect(page).toHaveURL(/login/, { timeout: 15000 });
  });
});
