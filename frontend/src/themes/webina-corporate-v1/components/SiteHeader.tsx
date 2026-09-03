'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { ChevronDown, Menu, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { LanguageMenu } from '@/components/LanguageMenu';
import { siteHref } from '@/lib/public-api-server';
import { COMPANY_MEGA, MEGA_MENUS, RESOURCE_MEGA, SERVICE_MEGA, SOLUTION_MEGA, type MegaDef } from '../site-nav';
import { cn } from '@/lib/utils';

export function SiteHeader({
  siteName,
  logoUrl,
}: {
  siteName: string;
  logoUrl?: string | null;
}) {
  const t = useTranslations();
  const [openId, setOpenId] = useState<string | null>(null);
  const [mobile, setMobile] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    document.body.style.overflow = mobile ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [mobile]);

  return (
    <header
      className={cn(
        'sticky top-0 z-50 text-white transition-[background,box-shadow,border-color] duration-300',
        scrolled
          ? 'border-b border-white/10 bg-[#07070a]/90 shadow-[0_12px_40px_-20px_#0066FF] backdrop-blur-xl'
          : 'border-b border-transparent bg-[#07070a]/70 backdrop-blur-md',
      )}
      onMouseLeave={() => setOpenId(null)}
    >
      <div className="mx-auto flex h-[4.25rem] max-w-7xl items-center justify-between gap-3 px-4 lg:px-6">
        <Link href={siteHref()} className="group flex min-w-0 items-center gap-3">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt={siteName} width={40} height={40} className="size-10 rounded-xl object-contain" />
          ) : (
            <span className="grid size-10 place-items-center rounded-xl bg-[#0066FF] text-sm font-black tracking-tight shadow-[0_0_24px_#0066FF88]">
              W
            </span>
          )}
          <span className="truncate text-lg font-semibold tracking-tight">{siteName}</span>
        </Link>

        <nav className="hidden items-center gap-0.5 lg:flex" aria-label={t('site.nav.home')}>
          <Link
            href={siteHref()}
            className="rounded-lg px-3 py-2 text-sm text-white/80 transition hover:bg-white/5 hover:text-white"
          >
            {t('site.nav.home')}
          </Link>
          {MEGA_MENUS.map((mega) => (
            <MegaTrigger
              key={mega.id}
              mega={mega}
              open={openId === mega.id}
              onOpen={() => setOpenId(mega.id)}
              label={t(mega.labelKey)}
            />
          ))}
          <Link
            href={siteHref(undefined, 'portfolio')}
            className="rounded-lg px-3 py-2 text-sm text-white/80 transition hover:bg-white/5 hover:text-white"
          >
            {t('site.nav.portfolio')}
          </Link>
        </nav>

        <div className="flex items-center gap-2">
          <LanguageMenu />
          <Button size="sm" asChild className="hidden bg-[#0066FF] shadow-[0_0_20px_#0066FF55] hover:bg-[#0052cc] sm:inline-flex">
            <Link href={siteHref(undefined, 'consultation')}>{t('site.nav.freeConsultation')}</Link>
          </Button>
          <Button
            type="button"
            size="icon"
            variant="outline"
            className="border-white/15 bg-white/5 text-white lg:hidden"
            aria-label={mobile ? t('site.nav.closeMenu') : t('site.nav.openMenu')}
            onClick={() => setMobile((v) => !v)}
          >
            {mobile ? <X className="size-5" /> : <Menu className="size-5" />}
          </Button>
        </div>
      </div>

      {openId ? (
        <MegaPanel mega={MEGA_MENUS.find((m) => m.id === openId)!} t={t} />
      ) : null}

      {mobile ? (
        <MobileNav
          t={t}
          onClose={() => setMobile(false)}
        />
      ) : null}
    </header>
  );
}

function MegaTrigger({
  mega,
  open,
  onOpen,
  label,
}: {
  mega: MegaDef;
  open: boolean;
  onOpen: () => void;
  label: string;
}) {
  return (
    <button
      type="button"
      className={cn(
        'flex items-center gap-1 rounded-lg px-3 py-2 text-sm transition',
        open ? 'bg-white/10 text-white' : 'text-white/80 hover:bg-white/5 hover:text-white',
      )}
      onMouseEnter={onOpen}
      onFocus={onOpen}
      aria-expanded={open}
    >
      {label}
      <ChevronDown className={cn('size-3.5 opacity-70 transition', open && 'rotate-180')} />
    </button>
  );
}

function MegaPanel({ mega, t }: { mega: MegaDef; t: (key: string) => string }) {
  const cols = mega.columns.length;
  return (
    <div className="absolute inset-x-0 top-full border-t border-white/10 bg-[#0b0d12]/97 shadow-2xl shadow-black/50 backdrop-blur-xl">
      <div className="mx-auto max-w-7xl px-4 py-8 lg:px-6">
        <div
          className={cn(
            'grid gap-8',
            cols >= 5 ? 'lg:grid-cols-5' : cols === 4 ? 'lg:grid-cols-4' : cols === 2 ? 'lg:grid-cols-2' : 'lg:grid-cols-3',
          )}
        >
          {mega.columns.map((col) => (
            <div key={col.titleKey}>
              {col.href ? (
                <Link href={siteHref(undefined, col.href)} className="text-sm font-semibold text-[#6ea8ff] hover:text-white">
                  {t(col.titleKey)}
                </Link>
              ) : (
                <p className="text-sm font-semibold text-[#6ea8ff]">{t(col.titleKey)}</p>
              )}
              <ul className="mt-3 space-y-1.5">
                {col.items.map((item) => (
                  <li key={item.href}>
                    <Link
                      href={siteHref(undefined, item.href)}
                      className="block rounded-md px-1 py-1 text-sm text-white/70 transition hover:bg-white/5 hover:text-white"
                    >
                      {t(item.labelKey)}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5">
          <p className="max-w-xl text-sm text-white/50">{t('site.mega.panelHint')}</p>
          <Link
            href={siteHref(undefined, mega.href)}
            className="text-sm font-medium text-[#0066FF] hover:underline"
          >
            {t('site.mega.seeAll')} →
          </Link>
        </div>
      </div>
    </div>
  );
}

function MobileNav({ t, onClose }: { t: (key: string) => string; onClose: () => void }) {
  const [open, setOpen] = useState<string | null>(SERVICE_MEGA.id);
  const groups = [SERVICE_MEGA, SOLUTION_MEGA, RESOURCE_MEGA, COMPANY_MEGA];

  return (
    <div className="fixed inset-x-0 top-[4.25rem] bottom-0 z-40 overflow-y-auto bg-[#07070a] lg:hidden">
      <div className="space-y-1 px-4 py-4">
        <Link href={siteHref()} onClick={onClose} className="block rounded-xl px-3 py-3 text-white/90 hover:bg-white/5">
          {t('site.nav.home')}
        </Link>
        <Link href={siteHref(undefined, 'portfolio')} onClick={onClose} className="block rounded-xl px-3 py-3 text-white/90 hover:bg-white/5">
          {t('site.nav.portfolio')}
        </Link>
        {groups.map((mega) => (
          <div key={mega.id} className="rounded-xl border border-white/10">
            <button
              type="button"
              className="flex w-full items-center justify-between px-3 py-3 text-start text-white"
              onClick={() => setOpen((id) => (id === mega.id ? null : mega.id))}
            >
              {t(mega.labelKey)}
              <ChevronDown className={cn('size-4 transition', open === mega.id && 'rotate-180')} />
            </button>
            {open === mega.id ? (
              <div className="space-y-4 border-t border-white/10 px-3 py-3">
                {mega.columns.map((col) => (
                  <div key={col.titleKey}>
                    <p className="text-xs font-semibold uppercase tracking-wide text-[#6ea8ff]">{t(col.titleKey)}</p>
                    <ul className="mt-2 space-y-1">
                      {col.items.map((item) => (
                        <li key={item.href}>
                          <Link
                            href={siteHref(undefined, item.href)}
                            onClick={onClose}
                            className="block py-1.5 text-sm text-white/70"
                          >
                            {t(item.labelKey)}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            ) : null}
          </div>
        ))}
        <Button asChild className="mt-4 w-full bg-[#0066FF] hover:bg-[#0052cc]">
          <Link href={siteHref(undefined, 'consultation')} onClick={onClose}>
            {t('site.nav.freeConsultation')}
          </Link>
        </Button>
      </div>
    </div>
  );
}
