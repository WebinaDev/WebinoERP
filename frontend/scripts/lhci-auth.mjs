const apiUrl = process.env.PLAYWRIGHT_API_URL || 'http://localhost/api';

/**
 * @param {import('puppeteer').Page} page
 */
module.exports = async (page) => {
  const url = page.url();
  if (!url.includes('/dashboard')) {
    return;
  }

  try {
    const res = await fetch(`${apiUrl}/v1/core/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: 'admin@webina.local', password: 'password' }),
    });
    if (!res.ok) return;
    const json = await res.json();
    const token = json?.data?.token;
    if (!token) return;

    await page.evaluateOnNewDocument((t) => {
      localStorage.setItem('auth_token', t);
    }, token);
    await page.reload({ waitUntil: 'networkidle0' });
  } catch {
    // Dashboard may render without API in perf-only runs
  }
};
