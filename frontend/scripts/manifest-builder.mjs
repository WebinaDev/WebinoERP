/**
 * Build route manifest entries from ROUTE_INVENTORY for PHPUnit + Playwright matrix tests.
 */

const API_WILDCARD_RESOLVE = {
  '/v1/core/chat/*': '/api/v1/core/chat/channels',
  '/v1/integrations/bale/*': '/api/webinocrm/v1/bale/settings',
  '/webinocrm/v1/hosting/*': '/api/webinocrm/v1/hosting/settings',
  '/v1/core/auth/*': null,
};

const API_HINT_OVERRIDES = {
  '/v1/core/users/me': '/api/v1/core/auth/user',
  '/v1/marketplace/gitea': '/api/v1/marketplace/gitea/settings',
  '/v1/integrations/modirpayamak/users': '/api/v1/integrations/modirpayamak/admin/dashboard',
  '/v1/integrations/modirpayamak/tickets': '/api/v1/integrations/modirpayamak/admin/dashboard',
  '/v1/integrations/modirpayamak/drafts': '/api/v1/integrations/modirpayamak/admin/dashboard',
  '/v1/integrations/modirpayamak/send': '/api/v1/integrations/modirpayamak/send',
  '/v1/integrations/modirpayamak/reports': '/api/v1/integrations/modirpayamak/reports',
  '/v1/integrations/modirpayamak/patterns': '/api/v1/integrations/modirpayamak/patterns',
  '/v1/integrations/modirpayamak/numbers': '/api/v1/integrations/modirpayamak/numbers',
  '/v1/integrations/modirpayamak/phonebooks': '/api/v1/integrations/modirpayamak/phonebooks',
  '/v1/integrations/modirpayamak/packages': '/api/v1/integrations/modirpayamak/admin/packages',
  '/v1/integrations/modirpayamak/orders': '/api/v1/integrations/modirpayamak/admin/orders',
  '/v1/integrations/modirpayamak/settings': '/api/v1/integrations/modirpayamak/settings',
  '/v1/integrations/modirpayamak/customers': '/api/v1/integrations/modirpayamak/admin/customers',
};

/** Routes with dedicated Feature tests (beyond matrix smoke). */
const FEATURE_TEST_ROUTES = new Set([
  '',
  'hrm/staff',
  'finance/journals',
  'crm/deals',
  'pm/projects',
  'scm/inbound',
  'sales/invoices',
  'admin/integrations/modirpayamak',
  'admin/integrations/modirpayamak/send',
  'admin/integrations/modirpayamak/tickets',
  'admin/integrations/modirpayamak/users',
  'admin/integrations/modirpayamak/drafts',
  'docs/contracts',
  'admin/marketplace/modules/new',
  'admin/licenses',
  'admin/settings',
  'admin/integrations/bale',
  'mfg',
  'mfg/boms',
  'mfg/work-orders',
  'mfg/quality',
  'mfg/planning',
  'admin/hosting-infra',
]);

function resolveE2ePath(route) {
  return route
    .replace(/:id/g, '1')
    .replace(/:tab\?/g, 'general');
}

function resolveApiPath(apiHint) {
  if (!apiHint || apiHint === '—') return null;
  if (API_HINT_OVERRIDES[apiHint]) return API_HINT_OVERRIDES[apiHint];
  if (API_WILDCARD_RESOLVE[apiHint] !== undefined) return API_WILDCARD_RESOLVE[apiHint];

  let path = apiHint;
  if (path.startsWith('/webinocrm/')) {
    path = `/api${path}`;
  } else if (path.startsWith('/v1/')) {
    path = `/api${path}`;
  }
  path = path.replace(/\{id\}/g, '1');
  if (path.includes('*')) return null;
  return path;
}

function inferRole(row) {
  if (row.route.startsWith('finance') || row.module === 'finance') return 'finance_manager';
  if (row.route === 'admin/hosting-infra') return 'system_manager';
  return 'system_manager';
}

function needsMfgModule(row) {
  return row.module === 'mfg' || row.route.startsWith('mfg/');
}

/**
 * @param {import('./route-inventory.mjs').ROUTE_INVENTORY} inventory
 */
export function buildManifestFromInventory(inventory) {
  const routes = [];
  const seen = new Set();

  for (const row of inventory) {
    const route = row.route;
    if (seen.has(route)) continue;
    seen.add(route);

    const e2ePath = resolveE2ePath(route);
    const apiPath = resolveApiPath(row.api);
    const e2eAuth = route !== 'login';
    const isDetail = route.includes(':id') || row.api?.includes('{id}');

    const entry = {
      route,
      module: row.module,
      apiHint: row.api,
      e2ePath,
      e2eAuth,
      component: row.component,
    };

    if (apiPath) {
      entry.apiSmoke = {
        method: 'GET',
        path: apiPath,
        role: inferRole(row),
        allowNotFound: isDetail,
      };
      if (needsMfgModule(row)) {
        entry.apiSmoke.requiresModules = ['mfg'];
      }
    }

    if (FEATURE_TEST_ROUTES.has(route)) {
      entry.featureTest = true;
    }

    routes.push(entry);
  }

  return {
    generatedAt: new Date().toISOString(),
    version: 1,
    routes,
  };
}

/** Compute suggested tests column for inventory row after matrix coverage. */
export function suggestTestsStatus(row, manifestRoutes) {
  const m = manifestRoutes.find((r) => r.route === row.route);
  if (!m) return row.tests ?? '❌';
  if (m.featureTest && m.apiSmoke) return '✅';
  if (m.apiSmoke || m.e2eAuth) return '🟡';
  return '❌';
}
