import { chromium, type FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

async function globalSetup(config: FullConfig) {
  const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? config.projects[0]?.use?.baseURL ?? 'http://localhost:3000';
  const apiURL = process.env.PLAYWRIGHT_API_URL ?? 'http://localhost/api';
  const authDir = path.join(__dirname, '.auth');
  const authFile = path.join(authDir, 'admin.json');

  fs.mkdirSync(authDir, { recursive: true });

  const emptyState = { cookies: [] as [], origins: [] as [] };

  try {
    const loginRes = await fetch(`${apiURL}/v1/core/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: 'admin@webina.local', password: 'password' }),
    });

    if (!loginRes.ok) {
      console.warn(`globalSetup: API login failed (${loginRes.status})`);
      fs.writeFileSync(authFile, JSON.stringify(emptyState));
      return;
    }

    const json = (await loginRes.json()) as { data?: { token?: string } };
    const token = json?.data?.token;
    if (!token) {
      fs.writeFileSync(authFile, JSON.stringify(emptyState));
      return;
    }

    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto(`${baseURL}/login`);
    await page.evaluate((t) => localStorage.setItem('auth_token', t), token);
    await page.context().storageState({ path: authFile });
    await browser.close();
  } catch (err) {
    console.warn('globalSetup: could not seed auth storageState', err);
    fs.writeFileSync(authFile, JSON.stringify(emptyState));
  }
}

export default globalSetup;
