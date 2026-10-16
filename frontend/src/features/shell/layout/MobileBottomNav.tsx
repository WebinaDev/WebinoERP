'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import {
  ChartColumn,
  LayoutDashboard,
  LayoutGrid,
  ListTodo,
  UsersRound,
  type LucideIcon,
} from 'lucide-react';
import { usePathname } from '@/lib/i18n-navigation';
import { dashboardHref } from '@/lib/route-resolver';
import { cn } from '@/lib/utils';

/** Single icon family (Lucide) + one stroke for the whole tab bar. */
const ICON_STROKE = 1.75;

type Tab = {
  id: string;
  href?: string;
  labelKey: 'home' | 'crm' | 'tasks' | 'reports' | 'more';
  icon: LucideIcon;
  match?: (path: string) => boolean;
  action?: 'menu';
};

const TABS: Tab[] = [
  {
    id: 'home',
    href: '',
    labelKey: 'home',
    icon: LayoutDashboard,
    match: (p) => {
      const bare = p.replace(/\/+$/, '') || '/';
      return bare === '/dashboard' || bare === '/admin' || bare === '/';
    },
  },
  {
    id: 'crm',
    href: 'crm/leads',
    labelKey: 'crm',
    icon: UsersRound,
    match: (p) => p.includes('/crm'),
  },
  {
    id: 'tasks',
    href: 'pm/tasks',
    labelKey: 'tasks',
    icon: ListTodo,
    match: (p) => p.includes('/pm'),
  },
  {
    id: 'reports',
    href: 'reports',
    labelKey: 'reports',
    icon: ChartColumn,
    match: (p) => p.includes('/reports'),
  },
  {
    id: 'more',
    labelKey: 'more',
    icon: LayoutGrid,
    action: 'menu',
  },
];

function normalizePath(pathname: string): string {
  return pathname || '/';
}

type Props = {
  moreOpen?: boolean;
  onMoreOpenChange?: (open: boolean) => void;
};

export function MobileBottomNav({ moreOpen = false, onMoreOpenChange }: Props) {
  const t = useTranslations('shell.mobileNav');
  const locale = useLocale();
  const pathname = usePathname() ?? '';
  const path = normalizePath(pathname);

  return (
    <nav
      className="wd-mobile-tabbar md:hidden"
      aria-label={t('aria')}
      dir={locale === 'fa' ? 'rtl' : 'ltr'}
    >
      {TABS.map((tab) => {
        const Icon = tab.icon;
        const active = tab.action === 'menu' ? moreOpen : Boolean(tab.match?.(path));
        const label = t(tab.labelKey);

        if (tab.action === 'menu') {
          return (
            <button
              key={tab.id}
              type="button"
              className={cn('wd-mobile-tab', active && 'on')}
              onClick={() => onMoreOpenChange?.(!moreOpen)}
              aria-label={label}
              aria-expanded={moreOpen}
              aria-haspopup="dialog"
            >
              <span className="wd-mobile-tab-ic">
                <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} />
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
            onClick={() => {
              if (moreOpen) onMoreOpenChange?.(false);
            }}
          >
            <span className="wd-mobile-tab-ic">
              <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} />
            </span>
            <span className="wd-mobile-tab-label">{label}</span>
          </Link>
        );
      })}
    </nav>
  );
}
