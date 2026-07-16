import { chromium, type FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const COOKIE_NAME = process.env.AUTH_COOKIE_NAME ?? 'webino_auth_token';

function cookieValueFromSetCookie(headers: Headers, name: string): string | null {
  const raw =
    typeof headers.getSetCookie === 'function'
      ? headers.getSetCookie()
      : (() => {
          const single = headers.get('set-cookie');
          return single ? [single] : [];
        })();

  for (const line of raw) {
    const match = line.match(new RegExp(`(?:^|,\\s*)${name}=([^;]+)`));
    if (match?.[1]) {
      return decodeURIComponent(match[1]);
    }
  }
  return null;
}

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

    const token = cookieValueFromSetCookie(loginRes.headers, COOKIE_NAME);
    if (!token) {
      console.warn('globalSetup: auth cookie missing from Set-Cookie');
      fs.writeFileSync(authFile, JSON.stringify(emptyState));
      return;
    }

    const apiHost = new URL(apiURL).hostname;
    const frontHost = new URL(baseURL).hostname;

    const browser = await chromium.launch();
    const context = await browser.newContext();
    await context.addCookies([
      {
        name: COOKIE_NAME,
        value: token,
        domain: apiHost,
        path: '/',
        httpOnly: true,
        secure: false,
        sameSite: 'Lax',
      },
      ...(apiHost !== frontHost
        ? [
            {
              name: COOKIE_NAME,
              value: token,
              domain: frontHost,
              path: '/',
              httpOnly: true,
              secure: false,
              sameSite: 'Lax' as const,
            },
          ]
        : []),
    ]);
    const page = await context.newPage();
    await page.goto(`${baseURL}/login`);
    await context.storageState({ path: authFile });
    await browser.close();
  } catch (err) {
    console.warn('globalSetup: could not seed auth storageState', err);
    fs.writeFileSync(authFile, JSON.stringify(emptyState));
  }
}

export default globalSetup;
