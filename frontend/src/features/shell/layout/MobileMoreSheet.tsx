'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import {
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Languages,
  LayoutDashboard,
  LogOut,
  Moon,
  Palette,
  Settings2,
  Sun,
  Laptop,
  User,
  X,
  type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTheme } from 'next-themes';
import { useQueryClient } from '@tanstack/react-query';
import { usePathname, useRouter } from '@/lib/i18n-navigation';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { AppSidebarModuleSection } from '@/components/blocks/sidebar-07/components/app-sidebar';
import type { Sidebar08MainNavItem, Sidebar08NavSubItem } from '@/lib/nav-modules';
import {
  ACCENT_MENU_ITEMS,
  ACCENT_SWATCH,
  isBusinessAccent,
  normalizeAccent,
  type AccentPreset,
} from '@/lib/accent';
import { setAccent as applyAccentProvider } from '@/components/providers/theme-provider';
import { useBootstrapQuery } from '@/hooks/useBootstrapQuery';
import { apiFetch } from '@/lib/api';
import { patchBootstrapQuery } from '@/lib/bootstrapQuery';
import { normalizeUiLocale } from '@/lib/locale';
import { cn } from '@/lib/utils';

const ICON_STROKE = 1.75;

type MoreTile = {
  id: string;
  title: string;
  url: string;
  icon: LucideIcon;
};

type MoreSection = {
  id: string;
  label: string;
  tiles: MoreTile[];
};

type View = 'menu' | 'settings';

function firstLeafUrl(item: Sidebar08MainNavItem | Sidebar08NavSubItem): string | null {
  if (item.url && item.url !== '#') return item.url;
  const kids = 'items' in item ? item.items : undefined;
  if (!kids?.length) return null;
  for (const k of kids) {
    const u = firstLeafUrl(k);
    if (u) return u;
  }
  return null;
}

function flattenItem(item: Sidebar08MainNavItem, out: MoreTile[]) {
  if (item.items?.length) {
    for (const child of item.items) {
      if (child.items?.length) {
        for (const nested of child.items) {
          const url = firstLeafUrl(nested);
          if (url) out.push({ id: nested.id, title: nested.title, url, icon: item.icon });
        }
      } else {
        const url = firstLeafUrl(child);
        if (url) out.push({ id: child.id, title: child.title, url, icon: item.icon });
      }
    }
    return;
  }
  const url = firstLeafUrl(item);
  if (url) out.push({ id: item.id, title: item.title, url, icon: item.icon });
}

function buildSections(
  pinnedItems: Sidebar08MainNavItem[],
  moduleSections: AppSidebarModuleSection[],
  dashboardLabel: string,
): MoreSection[] {
  const sections: MoreSection[] = [];

  if (pinnedItems.length) {
    const tiles: MoreTile[] = [];
    for (const item of pinnedItems) flattenItem(item, tiles);
    if (tiles.length) sections.push({ id: 'pinned', label: dashboardLabel, tiles });
  }

  for (const sec of moduleSections) {
    const tiles: MoreTile[] = [];
    for (const item of sec.items) flattenItem(item, tiles);
    if (tiles.length) {
      sections.push({
        id: sec.id,
        label: sec.label?.trim() || sec.id,
        tiles,
      });
    }
  }

  return sections;
}

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pinnedItems: Sidebar08MainNavItem[];
  moduleSections: AppSidebarModuleSection[];
  user: { name: string; email: string; role?: string };
  logoutLabel: string;
  onLogout: () => void | Promise<void>;
};

export function MobileMoreSheet({
  open,
  onOpenChange,
  pinnedItems,
  moduleSections,
  user,
  logoutLabel,
  onLogout,
}: Props) {
  const t = useTranslations();
  const locale = useLocale();
  const rtl = locale === 'fa';
  const pathname = usePathname() ?? '';
  const router = useRouter();
  const [view, setView] = useState<View>('menu');

  useEffect(() => {
    if (!open) setView('menu');
  }, [open]);

  const sections = useMemo(
    () => buildSections(pinnedItems, moduleSections, t('nav.erp.dashboard')),
    [pinnedItems, moduleSections, t],
  );

  const initials = (user.name || 'U')
    .split(/\s+/)
    .map((p) => p[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

  function close() {
    onOpenChange(false);
  }

  function go(url: string) {
    close();
    if (url && url !== '#') router.push(url);
  }

  const BackIcon = rtl ? ChevronRight : ChevronLeft;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="bottom"
        showCloseButton={false}
        className="wd-mobile-more-sheet flex h-[min(94dvh,100%)] max-h-[94dvh] w-full max-w-none flex-col gap-0 overflow-hidden rounded-t-2xl border-x-0 border-b-0 p-0"
      >
        <SheetHeader className="sr-only">
          <SheetTitle>{view === 'settings' ? t('shell.mobileSettings.title') : t('shell.mobileMore.title')}</SheetTitle>
          <SheetDescription>{t('shell.mobileMore.description')}</SheetDescription>
        </SheetHeader>

        <div className="wd-mobile-more-bar flex shrink-0 items-center gap-2 border-b border-[var(--wd-mobile-line,#e5e7eb)] px-3 py-3">
          {view === 'settings' ? (
            <button
              type="button"
              className="wd-mobile-more-icon-btn"
              onClick={() => setView('menu')}
              aria-label={t('shell.mobileSettings.back')}
            >
              <BackIcon absoluteStrokeWidth strokeWidth={ICON_STROKE} className="size-5" />
            </button>
          ) : (
            <span className="size-9" aria-hidden />
          )}
          <p className="min-w-0 flex-1 truncate text-center text-[15px] font-semibold tracking-tight">
            {view === 'settings' ? t('shell.mobileSettings.title') : t('shell.mobileMore.title')}
          </p>
          <button type="button" className="wd-mobile-more-icon-btn" onClick={close} aria-label={t('shell.mobileMore.close')}>
            <X absoluteStrokeWidth strokeWidth={ICON_STROKE} className="size-5" />
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain px-3 pb-[calc(12px+env(safe-area-inset-bottom,0px))] pt-3">
          {view === 'menu' ? (
            <MenuView
              sections={sections}
              pathname={pathname}
              userName={user.name}
              userEmail={user.email}
              userRole={user.role}
              initials={initials}
              onOpenSettings={() => setView('settings')}
              onGo={go}
              onLogout={async () => {
                close();
                await onLogout();
              }}
              logoutLabel={logoutLabel}
            />
          ) : (
            <SettingsView />
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}

function MenuView({
  sections,
  pathname,
  userName,
  userEmail,
  userRole,
  initials,
  onOpenSettings,
  onGo,
  onLogout,
  logoutLabel,
}: {
  sections: MoreSection[];
  pathname: string;
  userName: string;
  userEmail: string;
  userRole?: string;
  initials: string;
  onOpenSettings: () => void;
  onGo: (url: string) => void;
  onLogout: () => void | Promise<void>;
  logoutLabel: string;
}) {
  const t = useTranslations();

  return (
    <div className="flex flex-col gap-5">
      <button type="button" className="wd-mobile-more-profile" onClick={onOpenSettings}>
        <span className="wd-mobile-more-avatar" aria-hidden>
          {initials}
        </span>
        <span className="min-w-0 flex-1 text-start">
          <span className="block truncate text-[14px] font-semibold leading-tight">{userName}</span>
          <span className="mt-0.5 block truncate text-[12px] text-[var(--wd-mobile-hint,#6b7280)]">
            {userRole || userEmail || t('shell.mobileSettings.title')}
          </span>
        </span>
        <Settings2 absoluteStrokeWidth strokeWidth={ICON_STROKE} className="size-5 shrink-0 text-[var(--wd-brand,#0054ff)]" />
      </button>

      {sections.map((section) => (
        <section key={section.id} className="wd-mobile-more-section">
          <h3 className="wd-mobile-more-section-title">{section.label}</h3>
          <div className="wd-mobile-more-grid">
            {section.tiles.map((tile) => {
              const Icon = tile.icon || LayoutDashboard;
              const active =
                tile.url !== '#' &&
                (pathname === tile.url ||
                  pathname.endsWith(tile.url) ||
                  (tile.url !== '/dashboard' && pathname.includes(tile.url.replace(/^\//, ''))));
              return (
                <button
                  key={tile.id}
                  type="button"
                  className={cn('wd-mobile-more-tile', active && 'on')}
                  onClick={() => onGo(tile.url)}
                >
                  <span className="wd-mobile-more-tile-ic">
                    <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} />
                  </span>
                  <span className="wd-mobile-more-tile-label">{tile.title}</span>
                </button>
              );
            })}
          </div>
        </section>
      ))}

      <section className="wd-mobile-more-section">
        <h3 className="wd-mobile-more-section-title">{t('shell.mobileMore.account')}</h3>
        <div className="wd-mobile-more-grid">
          <button type="button" className="wd-mobile-more-tile" onClick={onOpenSettings}>
            <span className="wd-mobile-more-tile-ic">
              <Settings2 absoluteStrokeWidth strokeWidth={ICON_STROKE} />
            </span>
            <span className="wd-mobile-more-tile-label">{t('shell.mobileSettings.title')}</span>
          </button>
          <button type="button" className="wd-mobile-more-tile" onClick={() => onGo('/dashboard/profile')}>
            <span className="wd-mobile-more-tile-ic">
              <User absoluteStrokeWidth strokeWidth={ICON_STROKE} />
            </span>
            <span className="wd-mobile-more-tile-label">{t('nav.erp.profile')}</span>
          </button>
          <a href="/" target="_blank" rel="noreferrer" className="wd-mobile-more-tile" onClick={() => {}}>
            <span className="wd-mobile-more-tile-ic">
              <ExternalLink absoluteStrokeWidth strokeWidth={ICON_STROKE} />
            </span>
            <span className="wd-mobile-more-tile-label">{t('nav.visitSite')}</span>
          </a>
          <button type="button" className="wd-mobile-more-tile danger" onClick={() => void onLogout()}>
            <span className="wd-mobile-more-tile-ic">
              <LogOut absoluteStrokeWidth strokeWidth={ICON_STROKE} />
            </span>
            <span className="wd-mobile-more-tile-label">{logoutLabel}</span>
          </button>
        </div>
      </section>
    </div>
  );
}

function SettingsView() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const { theme, setTheme, resolvedTheme } = useTheme();
  const qc = useQueryClient();
  const bq = useBootstrapQuery();
  const themeValue = (theme ?? 'system') as 'light' | 'dark' | 'system';
  const [accent, setAccentState] = useState<AccentPreset>('colorful');

  useEffect(() => {
    setAccentState(normalizeAccent(bq.data?.uiAccent));
  }, [bq.data?.uiAccent]);

  const saveTheme = useCallback(
    (value: 'light' | 'dark' | 'system') => {
      setTheme(value);
      patchBootstrapQuery(qc, { uiTheme: value });
      void apiFetch('settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ui_theme: value }),
      }).catch(() => {});
    },
    [setTheme, qc],
  );

  const saveAccent = useCallback(
    (value: AccentPreset) => {
      setAccentState(value);
      applyAccentProvider(value);
      try {
        localStorage.setItem('theme_accent', value);
      } catch {
        /* ignore */
      }
      patchBootstrapQuery(qc, { uiAccent: value });
      void apiFetch('settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ui_accent: value }),
      }).catch(() => {});
    },
    [qc],
  );

  function selectLocale(nextLocale: 'fa' | 'en') {
    document.cookie = `NEXT_LOCALE=${nextLocale};path=/;max-age=31536000`;
    localStorage.setItem('locale', nextLocale);
    void normalizeUiLocale(nextLocale);
    router.refresh();
  }

  const themeOptions = [
    { value: 'light' as const, label: t('settings.themeLight'), Icon: Sun },
    { value: 'dark' as const, label: t('settings.themeDark'), Icon: Moon },
    { value: 'system' as const, label: t('settings.themeSystem'), Icon: Laptop },
  ];

  return (
    <div className="flex flex-col gap-4">
      <p className="px-1 text-[12.5px] leading-relaxed text-[var(--wd-mobile-hint,#6b7280)]">
        {t('shell.mobileSettings.subtitle')}
      </p>

      <section className="wd-mobile-settings-card">
        <h3 className="wd-mobile-settings-card-title">
          <Sun className="size-4" absoluteStrokeWidth strokeWidth={ICON_STROKE} />
          {t('settings.theme')}
        </h3>
        <div className="wd-mobile-settings-segment" role="radiogroup" aria-label={t('settings.theme')}>
          {themeOptions.map(({ value, label, Icon }) => (
            <button
              key={value}
              type="button"
              role="radio"
              aria-checked={themeValue === value}
              className={cn('wd-mobile-settings-segment-btn', themeValue === value && 'on')}
              onClick={() => saveTheme(value)}
            >
              <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} className="size-4" />
              <span>{label}</span>
            </button>
          ))}
        </div>
        <p className="mt-2 text-[11.5px] text-[var(--wd-mobile-hint,#6b7280)]">
          {t('shell.mobileSettings.themeHint', {
            current: resolvedTheme === 'dark' ? t('settings.themeDark') : t('settings.themeLight'),
          })}
        </p>
      </section>

      <section className="wd-mobile-settings-card">
        <h3 className="wd-mobile-settings-card-title">
          <Palette className="size-4" absoluteStrokeWidth strokeWidth={ICON_STROKE} />
          {t('settings.accent')}
        </h3>
        <div className="wd-mobile-settings-swatches">
          {ACCENT_MENU_ITEMS.map((item) => (
            <button
              key={item.value}
              type="button"
              className={cn('wd-mobile-settings-swatch', accent === item.value && 'on')}
              onClick={() => saveAccent(item.value)}
              aria-label={t(item.labelKey)}
              title={t(item.labelKey)}
            >
              <span
                className={cn('wd-mobile-settings-swatch-dot', isBusinessAccent(item.value) && 'biz')}
                style={{ background: ACCENT_SWATCH[item.value] }}
              />
              <span className="wd-mobile-settings-swatch-label">{t(item.labelKey)}</span>
            </button>
          ))}
        </div>
      </section>

      <section className="wd-mobile-settings-card">
        <h3 className="wd-mobile-settings-card-title">
          <Languages className="size-4" absoluteStrokeWidth strokeWidth={ICON_STROKE} />
          {t('settings.language')}
        </h3>
        <div className="wd-mobile-settings-segment" role="radiogroup" aria-label={t('settings.language')}>
          <button
            type="button"
            role="radio"
            aria-checked={locale === 'fa'}
            className={cn('wd-mobile-settings-segment-btn', locale === 'fa' && 'on')}
            onClick={() => selectLocale('fa')}
          >
            <span aria-hidden>🇮🇷</span>
            <span>{t('settings.langFa')}</span>
          </button>
          <button
            type="button"
            role="radio"
            aria-checked={locale === 'en'}
            className={cn('wd-mobile-settings-segment-btn', locale === 'en' && 'on')}
            onClick={() => selectLocale('en')}
          >
            <span aria-hidden>🇬🇧</span>
            <span>{t('settings.langEn')}</span>
          </button>
        </div>
      </section>

      <section className="wd-mobile-settings-card">
        <h3 className="wd-mobile-settings-card-title">
          <Settings2 className="size-4" absoluteStrokeWidth strokeWidth={ICON_STROKE} />
          {t('shell.mobileSettings.more')}
        </h3>
        <div className="flex flex-col gap-1">
          <Link href="/dashboard/admin/settings" className="wd-mobile-settings-row">
            <span>{t('shell.mobileSettings.openAdmin')}</span>
            <ExternalLink className="size-4 opacity-60" absoluteStrokeWidth strokeWidth={ICON_STROKE} />
          </Link>
          <Link href="/dashboard/profile" className="wd-mobile-settings-row">
            <span>{t('nav.erp.profile')}</span>
            <User className="size-4 opacity-60" absoluteStrokeWidth strokeWidth={ICON_STROKE} />
          </Link>
        </div>
      </section>
    </div>
  );
}
