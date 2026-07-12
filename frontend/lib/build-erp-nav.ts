import type { DashboardModule } from '@/types/modules';
import { ERP_MODULES, ERP_DASHBOARD_ITEMS, ERP_SUBMODULES } from '@/lib/module-registry';
import { buildSidebarSections, navSectionsToSidebar08MainItems } from '@/lib/nav-modules';

type TranslateFn = (key: string) => string;

/** Build sidebar navigation tree from ERP module registry. */
export function buildErpNavigation(
  t: TranslateFn,
  enabledModules?: string[],
  can?: (permission: string) => boolean,
): DashboardModule[] {
  const enabled = new Set(enabledModules ?? ERP_MODULES.map((m) => m.id));
  const allow = (permission?: string) => !permission || !can || can(permission);

  const modules: DashboardModule[] = [
    {
      id: 'cat-dashboard',
      title: t('nav.erp.dashboard'),
      path: '',
      icon: 'ri-home-4-line',
      children: ERP_DASHBOARD_ITEMS.map((item) => ({
        id: item.id,
        title: t(item.titleKey),
        path: item.path,
        icon: item.icon,
        pinned: item.id === 'dashboard' || item.id === 'reports',
      })),
    },
  ];

  for (const mod of ERP_MODULES) {
    if (!enabled.has(mod.id)) continue;
    if (mod.id === 'admin' && can && !can('core.settings.view')) continue;
    const children: DashboardModule[] = mod.menuItems
      .filter((item) => allow(item.requiredPermission))
      .map((item) => ({
      id: item.id,
      title: t(item.titleKey),
      path: item.path,
      icon: item.icon,
      children: item.children?.filter((ch) => allow(ch.requiredPermission)).map((ch) => ({
        id: ch.id,
        title: t(ch.titleKey),
        path: ch.path,
        icon: ch.icon,
        children: ch.children?.map((nested) => ({
          id: nested.id,
          title: t(nested.titleKey),
          path: nested.path,
          icon: nested.icon,
        })),
      })),
    }));

    for (const sub of ERP_SUBMODULES) {
      if (sub.parentModuleId !== mod.id) continue;
      if (!enabled.has(sub.settingsKey) && !sub.defaultEnabled) continue;
      children.push({
        id: sub.menu.id,
        title: t(sub.menu.titleKey),
        path: sub.menu.path,
        icon: sub.menu.icon,
        children: sub.menu.children?.map((ch) => ({
          id: ch.id,
          title: t(ch.titleKey),
          path: ch.path,
          icon: ch.icon,
        })),
      });
    }

    modules.push({
      id: `cat-${mod.id}`,
      title: t(mod.sidebarCategoryKey),
      path: mod.basePath,
      icon: mod.menuItems[0]?.icon,
      children,
    });
  }

  return modules;
}

export function erpNavToSidebar(modules: DashboardModule[], t: TranslateFn, pathname: string) {
  const { pinned, sections } = buildSidebarSections(modules, t);
  return {
    pinned: navSectionsToSidebar08MainItems(pinned, pathname),
    sections: sections.map((s) => ({
      id: s.id,
      label: s.label,
      items: navSectionsToSidebar08MainItems(s.items, pathname),
    })),
  };
}
