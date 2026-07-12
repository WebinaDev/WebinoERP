'use client';

import { useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { AppSidebar } from '@/components/blocks/sidebar-07/components/app-sidebar';
import { LanguageMenu } from '@/components/LanguageMenu';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { Separator } from '@/components/ui/separator';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { buildErpNavigation } from '@/lib/build-erp-nav';
import { mapLicensedModulesToNavIds } from '@/lib/module-license-map';
import { buildSidebarSections, navSectionsToSidebar08MainItems } from '@/lib/nav-modules';
import { dashboardHref } from '@/lib/route-resolver';
import { resolveDashboardRoute } from '@/lib/dashboard-routes';
import { getCurrentUser, logout } from '@/lib/auth';
import { usePermissions } from '@/features/shared/hooks/usePermissions';
import { usePathname, useRouter } from '@/lib/i18n-navigation';

function toDashboardUrl(path: string): string {
  const raw = path.replace(/^\//, '');
  if (!raw) return '/dashboard';
  return `/dashboard/${raw}`;
}

export function DashboardShell({ children }: { children: React.ReactNode }) {
  const t = useTranslations();
  const pathname = usePathname() ?? '';
  const router = useRouter();
  const locale = useLocale();
  const rtl = locale === 'fa';

  const { can } = usePermissions();
  const [navLoading, setNavLoading] = useState(true);
  const [user, setUser] = useState({ name: 'User', email: '' });
  const [activeModules, setActiveModules] = useState<string[] | undefined>();

  useEffect(() => {
    let cancelled = false;
    async function load() {
      setNavLoading(true);
      const me = await getCurrentUser().catch(() => null);
      if (cancelled) return;
      if (me) {
        setUser({
          name: me.name ?? me.email ?? 'User',
          email: me.email ?? '',
        });
        if (me.licensed_modules?.length || me.active_modules?.length) {
          setActiveModules(mapLicensedModulesToNavIds(me.licensed_modules ?? me.active_modules ?? []));
        }
      }
      setNavLoading(false);
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  const modules = useMemo(() => buildErpNavigation(t, activeModules, can), [t, activeModules, can]);
  const { pinned, sections } = useMemo(() => buildSidebarSections(modules, t), [modules, t]);

  const pinnedItems = useMemo(
    () =>
      navSectionsToSidebar08MainItems(pinned, pathname).map((item) => ({
        ...item,
        url: toDashboardUrl(item.url.replace(/^\//, '')),
      })),
    [pinned, pathname],
  );

  const moduleSections = useMemo(
    () =>
      sections.map((section) => ({
        id: section.id,
        label: section.label,
        items: navSectionsToSidebar08MainItems(section.items, pathname).map((item) => ({
          ...item,
          url: item.url === '#' ? '#' : toDashboardUrl(item.url.replace(/^\//, '')),
          items: item.items?.map((sub) => ({
            ...sub,
            url: toDashboardUrl(sub.url.replace(/^\//, '')),
            items: sub.items?.map((nested) => ({
              ...nested,
              url: toDashboardUrl(nested.url.replace(/^\//, '')),
            })),
          })),
        })),
      })),
    [sections, pathname],
  );

  const crumbs = useMemo(() => {
    const base = pathname.replace(/^\/dashboard\/?/, '').replace(/^\//, '');
    const parts = base ? base.split('/').filter(Boolean) : [];
    const out: { label: string; href?: string }[] = [
      { label: t('nav.erp.dashboard'), href: dashboardHref(locale, '') },
    ];
    let acc = '';
    for (const part of parts) {
      acc = acc ? `${acc}/${part}` : part;
      const meta = resolveDashboardRoute(acc);
      out.push({
        label: rtl ? meta.titleFa : meta.titleEn,
        href: dashboardHref(locale, acc),
      });
    }
    return out;
  }, [pathname, locale, t, rtl]);

  return (
    <SidebarProvider>
      <AppSidebar
        side={rtl ? 'right' : 'left'}
        brandTitle="Webino ERP"
        brandSubtitle={t('app.title')}
        brandTo={dashboardHref(locale, '')}
        pinnedItems={pinnedItems}
        moduleSections={moduleSections}
        navLoading={navLoading}
        user={user}
        logoutLabel={t('login.logout')}
        onLogout={async () => {
          await logout().catch(() => undefined);
          router.replace('/login');
        }}
      />
      <SidebarInset>
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger className="-ms-1" />
          <Separator orientation="vertical" className="me-2 h-4" />
          <Breadcrumb className="flex-1">
            <BreadcrumbList>
              {crumbs.map((crumb, i) => (
                <span key={crumb.href ?? crumb.label} className="contents">
                  {i > 0 ? <BreadcrumbSeparator /> : null}
                  <BreadcrumbItem>
                    {i === crumbs.length - 1 ? (
                      <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                    ) : (
                      <BreadcrumbLink href={crumb.href!}>{crumb.label}</BreadcrumbLink>
                    )}
                  </BreadcrumbItem>
                </span>
              ))}
            </BreadcrumbList>
          </Breadcrumb>
          <LanguageMenu />
        </header>
        <main className="flex flex-1 flex-col gap-4 p-4 md:p-6">{children}</main>
      </SidebarInset>
    </SidebarProvider>
  );
}
