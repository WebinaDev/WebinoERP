'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { BarChart3, CheckSquare, Home, Menu, Users } from 'lucide-react';
import { usePathname } from '@/lib/i18n-navigation';
import { dashboardHref } from '@/lib/route-resolver';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

type Tab = {
  id: string;
  href?: string;
  labelKey: 'home' | 'crm' | 'tasks' | 'reports' | 'more';
  icon: typeof Home;
  match?: (path: string) => boolean;
  action?: 'menu';
};

const TABS: Tab[] = [
  {
    id: 'home',
    href: '',
    labelKey: 'home',
    icon: Home,
    match: (p) => {
      const bare = p.replace(/\/+$/, '') || '/';
      return bare === '/dashboard' || bare === '/admin' || bare === '/';
    },
  },
  {
    id: 'crm',
    href: 'crm/leads',
    labelKey: 'crm',
    icon: Users,
    match: (p) => p.includes('/crm'),
  },
  {
    id: 'tasks',
    href: 'pm/tasks',
    labelKey: 'tasks',
    icon: CheckSquare,
    match: (p) => p.includes('/pm'),
  },
  {
    id: 'reports',
    href: 'reports',
    labelKey: 'reports',
    icon: BarChart3,
    match: (p) => p.includes('/reports'),
  },
  {
    id: 'more',
    labelKey: 'more',
    icon: Menu,
    action: 'menu',
  },
];

function normalizePath(pathname: string): string {
  return pathname || '/';
}

export function MobileBottomNav() {
  const t = useTranslations('shell.mobileNav');
  const locale = useLocale();
  const pathname = usePathname() ?? '';
  const path = normalizePath(pathname);
  const { toggleSidebar } = useSidebar();

  return (
    <nav
      className="wd-mobile-tabbar md:hidden"
      aria-label={t('aria')}
      dir={locale === 'fa' ? 'rtl' : 'ltr'}
    >
      {TABS.map((tab) => {
        const Icon = tab.icon;
        const active = tab.action === 'menu' ? false : Boolean(tab.match?.(path));
        const label = t(tab.labelKey);

        if (tab.action === 'menu') {
          return (
            <button
              key={tab.id}
              type="button"
              className="wd-mobile-tab"
              onClick={() => toggleSidebar()}
              aria-label={label}
            >
              <span className="wd-mobile-tab-ic">
                <Icon className="size-[18px]" strokeWidth={2.1} />
              </span>
              <span className="wd-mobile-tab-label">{label}</span>
            </button>
          );
        }

        const href = dashboardHref(locale, tab.href ?? '');
        return (
          <Link
            key={tab.id}
            href={href}
            className={cn('wd-mobile-tab', active && 'on')}
            aria-current={active ? 'page' : undefined}
          >
            <span className="wd-mobile-tab-ic">
              <Icon className="size-[18px]" strokeWidth={active ? 2.4 : 2.1} />
            </span>
            <span className="wd-mobile-tab-label">{label}</span>
          </Link>
        );
      })}
    </nav>
  );
}
