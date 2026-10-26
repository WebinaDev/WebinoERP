'use client';

import { ExternalLink, LayoutGrid, Maximize, Minimize } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { AppSidebar } from '@/components/blocks/sidebar-07/components/app-sidebar';
import { AccentMenu } from '@/components/AccentMenu';
import { LanguageMenu } from '@/components/LanguageMenu';
import { ThemeMenu } from '@/components/ThemeMenu';
import { Button } from '@/components/ui/button';
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
import { resolveLayoutNavKey } from '@/i18n/merge-locales';
import { getCurrentUser, logout } from '@/lib/auth';
import { htmlDir, sidebarSide } from '@/lib/locale';
import { usePermissions } from '@/features/shared/hooks/usePermissions';
import { usePathname, useRouter } from '@/lib/i18n-navigation';
import { NotificationBell } from '@/features/expansion/NotificationBell';
import { PwaRegistrar } from '@/features/expansion/PwaRegistrar';
import { MobileBottomNav } from '@/features/shell/layout/MobileBottomNav';
import { MobileMoreSheet } from '@/features/shell/layout/MobileMoreSheet';
import { GlobalSearchBox } from '@/features/modules/dashboard/GlobalSearchBox';
import { cn } from '@/lib/utils';
import {
  isPageBuilderFullscreenPath,
  isPageBuilderNewTabPath,
  openPageBuilderInNewTab,
} from '@/lib/page-builder-nav';
import './mobile-chrome.css';
import './desktop-chrome.css';

function toDashboardUrl(path: string): string {
  const raw = path.replace(/^\//, '');
  if (!raw) return '/dashboard';
  if (raw === 'dashboard' || raw.startsWith('dashboard/')) {
    return `/${raw}`;
  }
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
  const [user, setUser] = useState({ name: 'User', email: '', role: '' });
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
          role: me.dashboard_role ?? '',
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
      navSectionsToSidebar08MainItems(pinned, pathname).map((item) => {
        const url = toDashboardUrl(item.url.replace(/^\//, ''))
        return {
          ...item,
          url,
          openInNewTab: item.openInNewTab || isPageBuilderNewTabPath(url),
        }
      }),
    [pinned, pathname],
  );

  const moduleSections = useMemo(
    () =>
      sections.map((section) => ({
        id: section.id,
        label: section.label,
        items: navSectionsToSidebar08MainItems(section.items, pathname).map((item) => {
          const url = item.url === '#' ? '#' : toDashboardUrl(item.url.replace(/^\//, ''))
          return {
            ...item,
            url,
            openInNewTab: item.openInNewTab || (url !== '#' && isPageBuilderNewTabPath(url)),
            items: item.items?.map((sub) => {
              const subUrl = toDashboardUrl(sub.url.replace(/^\//, ''))
              return {
                ...sub,
                url: subUrl,
                openInNewTab: sub.openInNewTab || isPageBuilderNewTabPath(subUrl),
                items: sub.items?.map((nested) => {
                  const nestedUrl = toDashboardUrl(nested.url.replace(/^\//, ''))
                  return {
                    ...nested,
                    url: nestedUrl,
                    openInNewTab: nested.openInNewTab || isPageBuilderNewTabPath(nestedUrl),
                  }
                }),
              }
            }),
          }
        }),
      })),
    [sections, pathname],
  );

  const crumbs = useMemo(() => {
    const base = pathname.replace(/^\/(admin|dashboard)\/?/, '').replace(/^\//, '');
    const parts = base ? base.split('/').filter(Boolean) : [];
    const out: { label: string; href?: string }[] = [
      { label: t('nav.erp.dashboard'), href: dashboardHref(locale, '') },
    ];
    let acc = '';
    for (const part of parts) {
      acc = acc ? `${acc}/${part}` : part;
      const layoutKey = `layout.${resolveLayoutNavKey(part)}`;
      // Probe first: dynamic segments (page ids) have no message and next-intl logs MISSING_MESSAGE for each.
      const fromLayout = t.has(layoutKey) ? t(layoutKey) : layoutKey;
      const meta = resolveDashboardRoute(acc);
      out.push({
        label: fromLayout !== layoutKey ? fromLayout : rtl ? meta.titleFa : meta.titleEn,
        href: dashboardHref(locale, acc),
      });
    }
    return out;
  }, [pathname, locale, t, rtl]);

  const [fs, setFs] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  useEffect(() => {
    const h = () => setFs(Boolean(document.fullscreenElement));
    document.addEventListener('fullscreenchange', h);
    return () => document.removeEventListener('fullscreenchange', h);
  }, []);

  useEffect(() => {
    document.documentElement.dataset.wdDashboard = '1';
    return () => {
      delete document.documentElement.dataset.wdDashboard;
    };
  }, []);

  async function toggleFs() {
    try {
      if (!document.fullscreenElement) {
        await document.documentElement.requestFullscreen();
      } else {
        await document.exitFullscreen();
      }
    } catch {
      /* ignore */
    }
  }

  const pageTitle = crumbs[crumbs.length - 1]?.label ?? t('nav.erp.dashboard');

  // Safety net: any in-app <a> to صفحه‌ساز opens a new tab instead of replacing the dashboard.
  useEffect(() => {
    function onClick(event: MouseEvent) {
      const target = event.target
      if (!(target instanceof Element)) return
      const anchor = target.closest('a[href]')
      if (!(anchor instanceof HTMLAnchorElement)) return
      if (anchor.target === '_blank') return
      if (event.defaultPrevented || event.button !== 0) return
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
      const href = anchor.getAttribute('href')
      if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return
      if (!isPageBuilderNewTabPath(href)) return
      event.preventDefault()
      event.stopPropagation()
      openPageBuilderInNewTab(href)
    }
    document.addEventListener('click', onClick, true)
    return () => document.removeEventListener('click', onClick, true)
  }, [])

  // Editor surfaces are full-viewport; skip dashboard chrome when opened (incl. new tab).
  if (isPageBuilderFullscreenPath(pathname)) {
    return <>{children}</>
  }

  return (
    <SidebarProvider>
      <>
        <AppSidebar
          side={sidebarSide(locale)}
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
        <SidebarInset dir={htmlDir(locale)} className="wd-mobile-chrome wd-desktop-chrome">
          <header
            className={cn(
              'sticky top-0 z-30 flex shrink-0 items-center gap-2 border-b border-border/60 bg-background/80 backdrop-blur-md transition-[width,height] ease-linear supports-[backdrop-filter]:bg-background/70 group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12',
              'wd-mobile-topbar wd-desktop-topbar h-[52px] sm:h-14 md:h-14',
            )}
          >
            <div className="relative flex w-full min-w-0 items-center gap-2 px-3 sm:px-4 md:gap-3 md:px-5">
              <SidebarTrigger className="-ms-1 hidden md:inline-flex" />
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="-ms-1 inline-flex md:hidden"
                aria-label={t('shell.mobileMore.title')}
                onClick={() => setMoreOpen(true)}
              >
                <LayoutGrid className="size-5" strokeWidth={1.75} absoluteStrokeWidth />
              </Button>
              <Separator orientation="vertical" className="me-2 hidden data-[orientation=vertical]:h-4 md:block" />
              {/* Mobile: compact page title */}
              <div className="min-w-0 flex-1 md:hidden">
                <p className="wd-mobile-page-title truncate text-foreground">{pageTitle}</p>
              </div>
              {/* Desktop: page path sits where search used to (start / right of centered search in RTL) */}
              <Breadcrumb className="wd-desktop-path hidden min-w-0 md:block">
                <BreadcrumbList>
                  {crumbs.map((crumb, i) => (
                    <span key={crumb.href ?? crumb.label} className="contents">
                      {i > 0 ? <BreadcrumbSeparator className="hidden md:block" /> : null}
                      <BreadcrumbItem className={i === 0 ? 'hidden md:block' : undefined}>
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
              {/* Desktop: command search centered in the header */}
              <div className="wd-desktop-search hidden md:block">
                <GlobalSearchBox />
              </div>
              <div className="ms-auto flex items-center gap-1.5 sm:gap-2">
                <PwaRegistrar />
                <NotificationBell />
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  className="hidden xl:inline-flex"
                  aria-label={t('nav.fullscreen')}
                  onClick={() => void toggleFs()}
                >
                  {fs ? <Minimize className="size-4" strokeWidth={1.75} /> : <Maximize className="size-4" strokeWidth={1.75} />}
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  className="hidden md:inline-flex rounded-[10px]"
                  asChild
                >
                  <a href="/" target="_blank" rel="noreferrer" aria-label={t('nav.visitSite')}>
                    <ExternalLink className="size-3.5" strokeWidth={1.75} />
                  </a>
                </Button>
                <div className="hidden md:contents">
                  <LanguageMenu iconOnly />
                  <AccentMenu />
                  <ThemeMenu />
                </div>
              </div>
            </div>
          </header>
          <div className="@container/main wd-app-atmosphere wd-mobile-main wd-desktop-main wd-desktop-chrome flex min-w-0 max-w-full flex-1 flex-col gap-3 overflow-x-hidden p-3 sm:gap-4 sm:p-4 md:pb-4">
            {children}
          </div>
          <MobileBottomNav moreOpen={moreOpen} onMoreOpenChange={setMoreOpen} />
          <MobileMoreSheet
            open={moreOpen}
            onOpenChange={setMoreOpen}
            pinnedItems={pinnedItems}
            moduleSections={moduleSections}
            user={user}
            logoutLabel={t('login.logout')}
            onLogout={async () => {
              await logout().catch(() => undefined);
              router.replace('/login');
            }}
          />
        </SidebarInset>
      </>
    </SidebarProvider>
  );
}
