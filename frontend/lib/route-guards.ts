/**
 * Maps dashboard paths to required Spatie permissions for ModuleRouteGuard.
 */
export function resolveRoutePermission(path: string): string | undefined {
  const normalized = path.replace(/^\/+|\/+$/g, '');

  if (normalized === 'admin/logs' || normalized.startsWith('admin/logs/')) {
    return 'core.logs.view';
  }
  if (normalized === 'admin/licenses' || normalized.startsWith('admin/licenses/')) {
    return 'core.licenses.view';
  }
  if (normalized === 'admin/analytics/visitors' || normalized.startsWith('admin/analytics/')) {
    return 'core.visitor_stats.view';
  }
  if (normalized === 'admin/settings' || normalized.startsWith('admin/settings/')) {
    return 'core.settings.manage';
  }
  if (normalized.startsWith('admin/marketplace/')) {
    return 'marketplace.products.manage';
  }
  if (normalized.startsWith('admin/integrations/modirpayamak/send')
    || normalized.startsWith('admin/integrations/modirpayamak/customers')
    || normalized.startsWith('admin/integrations/modirpayamak/packages')) {
    return 'integrations.modirpayamak.manage';
  }
  if (normalized.startsWith('admin/integrations/modirpayamak')) {
    return 'integrations.modirpayamak.view';
  }

  return undefined;
}
