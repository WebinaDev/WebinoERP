'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { ArrowUpLeft, ChevronDown, Menu, X } from 'lucide-react';
import { LanguageMenu } from '@/components/LanguageMenu';
import { siteHref } from '@/lib/public-api-server';
import { cn } from '@/lib/utils';
import { COMPANY_MEGA, RESOURCE_MEGA, SERVICE_MEGA, SOLUTION_MEGA, type MegaDef } from '../site-nav';
import { LogoLockup } from './LogoLockup';
import { NavIcon } from './NavIcon';

/** Desktop order from the approved design: services · solutions · portfolio · resources · company. */
type NavEntry = { kind: 'mega'; mega: MegaDef } | { kind: 'link'; href: string; labelKey: string };

const NAV: NavEntry[] = [
  { kind: 'mega', mega: SERVICE_MEGA },
  { kind: 'mega', mega: SOLUTION_MEGA },
  { kind: 'link', href: 'portfolio', labelKey: 'site.nav.portfolio' },
  { kind: 'mega', mega: RESOURCE_MEGA },
  { kind: 'mega', mega: COMPANY_MEGA },
];

const MEGAS = [SERVICE_MEGA, SOLUTION_MEGA, RESOURCE_MEGA, COMPANY_MEGA];

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

  useEffect(() => {
    if (!openId) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpenId(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [openId]);

  const openMega = MEGAS.find((m) => m.id === openId);

  return (
    <header
      className={cn('site-header', (scrolled || openId) && 'is-scrolled')}
      onMouseLeave={() => setOpenId(null)}
    >
      <div className="webina-shell site-header-bar">
        <Link href={siteHref()} aria-label={siteName} onClick={() => setMobile(false)}>
          <LogoLockup siteName={siteName} logoUrl={logoUrl} />
        </Link>

        <nav className="desk-nav" aria-label={t('site.nav.mainMenu')}>
          {NAV.map((entry) =>
            entry.kind === 'link' ? (
              <Link
                key={entry.href}
                href={siteHref(undefined, entry.href)}
                className="nav-link"
                onMouseEnter={() => setOpenId(null)}
              >
                {t(entry.labelKey)}
              </Link>
            ) : (
              <button
                key={entry.mega.id}
                type="button"
                className={cn('nav-trigger', openId === entry.mega.id && 'is-open')}
                onMouseEnter={() => setOpenId(entry.mega.id)}
                onFocus={() => setOpenId(entry.mega.id)}
                onClick={() => setOpenId((id) => (id === entry.mega.id ? null : entry.mega.id))}
                aria-expanded={openId === entry.mega.id}
              >
                {t(entry.mega.labelKey)}
                <ChevronDown className={cn('size-3.5 opacity-60 transition', openId === entry.mega.id && 'rotate-180')} />
              </button>
            ),
          )}
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
            className="menu-toggle"
            aria-label={mobile ? t('site.nav.closeMenu') : t('site.nav.openMenu')}
            aria-expanded={mobile}
            onClick={() => setMobile((v) => !v)}
          >
            {mobile ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        </div>
      </div>

      {openMega ? <MegaPanel mega={openMega} t={t} onNavigate={() => setOpenId(null)} /> : null}

      {mobile ? <MobileNav t={t} onClose={() => setMobile(false)} /> : null}
    </header>
  );
}

function MegaPanel({ mega, t, onNavigate }: { mega: MegaDef; t: (key: string) => string; onNavigate: () => void }) {
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
                  <Link href={siteHref(undefined, col.href)} onClick={onNavigate}>
                    {t(col.titleKey)}
                  </Link>
                ) : (
                  <span>{t(col.titleKey)}</span>
                )}
              </h3>
              <p>{t(col.leadKey)}</p>
              <ul>
                {col.items.map((item) => (
                  <li key={item.href}>
                    <Link href={siteHref(undefined, item.href)} className="mega-item" onClick={onNavigate}>
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
            <Link href={siteHref(undefined, 'consultation')} className="mega-promo" onClick={onNavigate}>
              <small className="text-xs">{t('site.nav.freeConsultation')}</small>
              <strong className="mt-3 block text-xl leading-snug">{t('site.landing.finalTitle')}</strong>
              <span className="mt-3 flex items-center gap-1 text-sm">
                {t('site.mega.panelHint')}
                <ArrowUpLeft className="size-4 ltr:-scale-x-100" />
              </span>
            </Link>
          ) : null}
        </div>
        <div className="mega-foot">
          <p>{t('site.mega.panelHint')}</p>
          <Link href={siteHref(undefined, mega.href)} onClick={onNavigate}>
            {t('site.mega.seeAll')}
          </Link>
        </div>
      </div>
    </div>
  );
}

function MobileNav({ t, onClose }: { t: (key: string) => string; onClose: () => void }) {
  const [open, setOpen] = useState<string | null>(null);

  return (
    <div className="mobile-sheet min-[1100px]:hidden">
      <div className="webina-shell pb-10 pt-2">
        {MEGAS.slice(0, 2).map((mega) => (
          <MobileGroup key={mega.id} mega={mega} open={open === mega.id} onToggle={() => setOpen((id) => (id === mega.id ? null : mega.id))} t={t} onClose={onClose} />
        ))}
        <Link href={siteHref(undefined, 'portfolio')} onClick={onClose} className="mobile-link">
          {t('site.nav.portfolio')}
        </Link>
        <Link href={siteHref(undefined, 'products')} onClick={onClose} className="mobile-link">
          {t('site.nav.products')}
        </Link>
        {MEGAS.slice(2).map((mega) => (
          <MobileGroup key={mega.id} mega={mega} open={open === mega.id} onToggle={() => setOpen((id) => (id === mega.id ? null : mega.id))} t={t} onClose={onClose} />
        ))}
        <div className="mt-6 grid gap-2">
          <Link href={siteHref(undefined, 'consultation')} onClick={onClose} className="btn-primary w-full">
            {t('site.nav.freeConsultation')}
          </Link>
          <Link href={siteHref(undefined, 'proposal')} onClick={onClose} className="btn-ghost w-full">
            {t('site.nav.proposal')}
          </Link>
        </div>
      </div>
    </div>
  );
}

function MobileGroup({
  mega,
  open,
  onToggle,
  t,
  onClose,
}: {
  mega: MegaDef;
  open: boolean;
  onToggle: () => void;
  t: (key: string) => string;
  onClose: () => void;
}) {
  return (
    <div className="mobile-group">
      <button
        type="button"
        className="flex w-full items-center justify-between py-4 text-start"
        aria-expanded={open}
        onClick={onToggle}
      >
        {t(mega.labelKey)}
        <ChevronDown className={cn('size-5 text-[var(--brand-primary)] transition', open && 'rotate-180')} />
      </button>
      {open ? (
        <div className="space-y-5 pb-5">
          {mega.columns.map((col) => (
            <div key={col.titleKey}>
              {col.href ? (
                <Link href={siteHref(undefined, col.href)} onClick={onClose} className="mobile-group-title">
                  {t(col.titleKey)}
                </Link>
              ) : (
                <p className="mobile-group-title">{t(col.titleKey)}</p>
              )}
              <ul className="mt-2 grid grid-cols-1 gap-0.5 sm:grid-cols-2">
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
  );
}
