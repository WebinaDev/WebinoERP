'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { ChevronDown, Menu, X } from 'lucide-react';
import { LanguageMenu } from '@/components/LanguageMenu';
import { siteHref } from '@/lib/public-api-server';
import { cn } from '@/lib/utils';
import { COMPANY_MEGA, MEGA_MENUS, RESOURCE_MEGA, SERVICE_MEGA, SOLUTION_MEGA, type MegaDef } from '../site-nav';
import { LogoLockup } from './LogoLockup';
import { NavIcon } from './NavIcon';

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
    const onScroll = () => setScrolled(window.scrollY > 8);
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
      className={cn('site-header', scrolled && 'is-scrolled')}
      onMouseLeave={() => setOpenId(null)}
    >
      <div className="webina-shell site-header-bar">
        <Link href={siteHref()} aria-label={siteName}>
          <LogoLockup siteName={siteName} logoUrl={logoUrl} />
        </Link>

        <nav className="desk-nav" aria-label={t('site.nav.home')}>
          <Link href={siteHref()} className="nav-link">
            {t('site.nav.home')}
          </Link>
          {MEGA_MENUS.map((mega) => (
            <button
              key={mega.id}
              type="button"
              className={cn('nav-trigger', openId === mega.id && 'is-open')}
              onMouseEnter={() => setOpenId(mega.id)}
              onFocus={() => setOpenId(mega.id)}
              aria-expanded={openId === mega.id}
            >
              {t(mega.labelKey)}
              <ChevronDown className={cn('size-3.5 opacity-70 transition', openId === mega.id && 'rotate-180')} />
            </button>
          ))}
          <Link href={siteHref(undefined, 'products')} className="nav-link">
            {t('site.nav.products')}
          </Link>
          <Link href={siteHref(undefined, 'portfolio')} className="nav-link">
            {t('site.nav.portfolio')}
          </Link>
        </nav>

        <div className="flex items-center gap-2">
          <div className="lang-slot">
            <LanguageMenu />
          </div>
          <Link href={siteHref(undefined, 'consultation')} className="nav-cta">
            {t('site.nav.freeConsultation')}
          </Link>
          <button
            type="button"
            className="grid size-10 place-items-center rounded-full border border-white/15 text-white min-[1100px]:hidden"
            aria-label={mobile ? t('site.nav.closeMenu') : t('site.nav.openMenu')}
            onClick={() => setMobile((v) => !v)}
          >
            {mobile ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        </div>
      </div>

      {openId ? <MegaPanel mega={MEGA_MENUS.find((m) => m.id === openId)!} t={t} /> : null}

      {mobile ? <MobileNav t={t} onClose={() => setMobile(false)} /> : null}
    </header>
  );
}

function MegaPanel({ mega, t }: { mega: MegaDef; t: (key: string) => string }) {
  const cols = mega.columns.length >= 5 ? 'cols-5' : mega.columns.length === 1 ? 'cols-1' : 'cols-2';
  return (
    <div className="mega-sheet hidden min-[1100px]:block">
      <div className="webina-shell">
        <div className={cn('mega-grid', cols)}>
          {mega.columns.map((col) => (
            <div key={col.titleKey} className="mega-col">
              <h3 className="flex items-center gap-2">
                <span className="mega-ico">
                  <NavIcon name={col.icon} className="size-4" />
                </span>
                {col.href ? (
                  <Link href={siteHref(undefined, col.href)}>{t(col.titleKey)}</Link>
                ) : (
                  <span>{t(col.titleKey)}</span>
                )}
              </h3>
              <p>{t(col.leadKey)}</p>
              <ul>
                {col.items.map((item) => (
                  <li key={item.href}>
                    <Link href={siteHref(undefined, item.href)} className="mega-item">
                      <span className="mega-ico">
                        <NavIcon name={item.icon} className="size-3.5" />
                      </span>
                      {t(item.labelKey)}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
          {mega.columns.length < 5 ? (
            <Link href={siteHref(undefined, 'consultation')} className="panel" style={{ background: '#16130f', color: '#f6f1e8' }}>
              <p className="text-xs" style={{ color: '#e4c27a' }}>{t('site.nav.freeConsultation')}</p>
              <strong className="mt-2 block text-lg">{t('site.landing.finalTitle')}</strong>
              <span className="mt-2 block text-sm" style={{ color: 'rgba(246,241,232,.72)' }}>{t('site.mega.panelHint')}</span>
            </Link>
          ) : null}
        </div>
        <div className="mega-foot">
          <p>{t('site.mega.panelHint')}</p>
          <Link href={siteHref(undefined, mega.href)}>{t('site.mega.seeAll')}</Link>
        </div>
      </div>
    </div>
  );
}

function MobileNav({ t, onClose }: { t: (key: string) => string; onClose: () => void }) {
  const [open, setOpen] = useState<string | null>(SERVICE_MEGA.id);
  const groups = [SERVICE_MEGA, SOLUTION_MEGA, RESOURCE_MEGA, COMPANY_MEGA];

  return (
    <div className="mobile-sheet min-[1100px]:hidden">
      <div className="webina-shell py-4">
        <Link href={siteHref()} onClick={onClose} className="nav-link !text-inherit">
          {t('site.nav.home')}
        </Link>
        <Link href={siteHref(undefined, 'products')} onClick={onClose} className="mt-1 block rounded-xl px-3 py-3 hover:bg-black/5">
          {t('site.nav.products')}
        </Link>
        <Link href={siteHref(undefined, 'portfolio')} onClick={onClose} className="block rounded-xl px-3 py-3 hover:bg-black/5">
          {t('site.nav.portfolio')}
        </Link>
        {groups.map((mega) => (
          <div key={mega.id} className="mobile-group">
            <button
              type="button"
              className="flex w-full items-center justify-between px-3 py-3 text-start font-semibold"
              onClick={() => setOpen((id) => (id === mega.id ? null : mega.id))}
            >
              {t(mega.labelKey)}
              <ChevronDown className={cn('size-4 transition', open === mega.id && 'rotate-180')} />
            </button>
            {open === mega.id ? (
              <div className="space-y-4 border-t border-black/10 px-3 py-3">
                {mega.columns.map((col) => (
                  <div key={col.titleKey}>
                    <p className="text-xs font-bold text-[var(--saffron-deep)]">{t(col.titleKey)}</p>
                    <ul className="mt-2 space-y-1">
                      {col.items.map((item) => (
                        <li key={item.href}>
                          <Link href={siteHref(undefined, item.href)} onClick={onClose} className="mega-item">
                            <span className="mega-ico">
                              <NavIcon name={item.icon} className="size-3.5" />
                            </span>
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
        <Link href={siteHref(undefined, 'consultation')} onClick={onClose} className="btn-saffron mt-4 w-full">
          {t('site.nav.freeConsultation')}
        </Link>
      </div>
    </div>
  );
}
