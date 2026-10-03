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
  if (normalized.startsWith('admin/integrations/payments')) {
    return 'core.settings.manage';
  }
  if (normalized.startsWith('admin/integrations/modirpayamak')) {
    return 'integrations.modirpayamak.view';
  }


  if (normalized === 'hrm/shifts' || normalized === 'hrm/org-chart' || normalized === 'hrm/timesheets' || normalized === 'hrm/analytics') {
    return 'hrm.staff.view';
  }
  if (normalized === 'hrm/onboarding') return 'hrm.recruitment.view';
  if (normalized === 'hrm/okrs' || normalized === 'hrm/reviews-360') return 'hrm.performance.view';
  if (normalized === 'hrm/learning') return 'hrm.training.view';
  if (normalized.startsWith('hrm/my-')) return 'hrm.ess.view';

  return undefined;
}
