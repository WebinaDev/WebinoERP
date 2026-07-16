const apiUrl = process.env.PLAYWRIGHT_API_URL || process.env.NEXT_PUBLIC_API_URL || 'http://localhost/api';

/**
 * Cookie-auth seed for LHCI (HttpOnly `webino_auth_token`).
 * @param {import('puppeteer').Page} page
 */
module.exports = async (page) => {
  const url = page.url();
  if (!url.includes('/dashboard')) {
    return;
  }

  try {
    const res = await fetch(`${apiUrl.replace(/\/$/, '')}/v1/core/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        email: process.env.LHCI_LOGIN_EMAIL || 'admin@webina.local',
        password: process.env.LHCI_LOGIN_PASSWORD || 'password',
      }),
    });
    if (!res.ok) return;

    const setCookie = typeof res.headers.getSetCookie === 'function' ? res.headers.getSetCookie() : [];
    if (setCookie.length) {
      const cookies = setCookie.map((raw) => {
        const [pair] = raw.split(';');
        const [name, ...rest] = pair.split('=');
        return { name, value: rest.join('='), url: page.url() };
      });
      await page.setCookie(...cookies);
    }

    await page.reload({ waitUntil: 'networkidle0' });
  } catch {
    // Dashboard may render without API in perf-only runs
  }
};
