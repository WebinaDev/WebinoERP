'use client';

import Link from 'next/link';
import { useEffect, useId, useState } from 'react';
import { createPortal } from 'react-dom';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowUpLeft, ChevronDown, Menu, X } from 'lucide-react';
import { LanguageMenu } from '@/components/LanguageMenu';
import { siteHref } from '@/lib/public-api-server';
import { cn } from '@/lib/utils';
import { headerDefaults, isExternalHref, mergeContent, type HeaderData, type NavItemData } from '../site-content';
import { LogoLockup } from './LogoLockup';
import { NavIcon } from './NavIcon';

function go(path?: string) {
  if (!path) return siteHref();
  return isExternalHref(path) ? path : siteHref(undefined, path.replace(/^\//, ''));
}

/** Header content comes from site settings `branding.header` (page builder → سربرگ). */
export function SiteHeader({
  siteName,
  logoUrl,
  header,
}: {
  siteName: string;
  logoUrl?: string | null;
  header?: unknown;
}) {
  const t = useTranslations();
  const locale = useLocale();
  const data: HeaderData = locale === 'fa' ? mergeContent(headerDefaults(t), header) : headerDefaults(t);
  const [openId, setOpenId] = useState<number | null>(null);
  const [mobile, setMobile] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [portalReady, setPortalReady] = useState(false);
  const mobileNavId = useId();

  useEffect(() => {
    setPortalReady(true);
  }, []);

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
    if (openId === null && !mobile) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return;
      setOpenId(null);
      setMobile(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [openId, mobile]);

  const openMega = openId !== null ? data.nav[openId] : undefined;

  return (
    <header
      className={cn(
        'site-header',
        (scrolled || openId !== null || mobile) && 'is-scrolled',
        mobile && 'is-mobile-open',
      )}
      onMouseLeave={() => setOpenId(null)}
    >
      <div className="webina-shell site-header-bar">
        <Link href={siteHref()} aria-label={siteName} onClick={() => setMobile(false)}>
          <LogoLockup siteName={siteName} logoUrl={logoUrl} />
        </Link>

        <nav className="desk-nav" aria-label={t('site.nav.mainMenu')}>
          {data.nav.map((entry, i) =>
            !entry.columns?.length ? (
              <Link key={`${entry.href}-${i}`} href={go(entry.href)} className="nav-link" onMouseEnter={() => setOpenId(null)}>
                {entry.label}
              </Link>
            ) : (
              <button
                key={`${entry.href}-${i}`}
                type="button"
                className={cn('nav-trigger', openId === i && 'is-open')}
                onMouseEnter={() => setOpenId(i)}
                onFocus={() => setOpenId(i)}
                onClick={() => setOpenId((id) => (id === i ? null : i))}
                aria-expanded={openId === i}
              >
                {entry.label}
                <ChevronDown className={cn('size-3.5 opacity-60 transition', openId === i && 'rotate-180')} />
              </button>
            ),
          )}
        </nav>

        <div className="flex items-center gap-2">
          <div className="lang-slot">
            <LanguageMenu />
          </div>
          {data.cta_label ? (
            <Link href={go(data.cta_href)} className="nav-cta">
              {data.cta_label}
            </Link>
          ) : null}
          <button
            type="button"
            className="menu-toggle"
            aria-label={mobile ? t('site.nav.closeMenu') : t('site.nav.openMenu')}
            aria-expanded={mobile}
            aria-controls={mobileNavId}
            onClick={() => {
              setOpenId(null);
              setMobile((v) => !v);
            }}
          >
            {mobile ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        </div>
      </div>

      {openMega?.columns?.length ? <MegaPanel mega={openMega} data={data} onNavigate={() => setOpenId(null)} /> : null}

      {portalReady && mobile
        ? createPortal(
            <MobileNav
              id={mobileNavId}
              data={data}
              closeLabel={t('site.nav.closeMenu')}
              onClose={() => setMobile(false)}
            />,
            document.body,
          )
        : null}
    </header>
  );
}

function MegaPanel({ mega, data, onNavigate }: { mega: NavItemData; data: HeaderData; onNavigate: () => void }) {
  const columns = mega.columns ?? [];
  const cols = columns.length >= 5 ? 'cols-5' : columns.length === 1 ? 'cols-1' : 'cols-2';
  return (
    <div className="mega-sheet hidden min-[1100px]:block">
      <div className="webina-shell">
        <div className={cn('mega-grid', cols)}>
          {columns.map((col, ci) => (
            <div key={`${col.title}-${ci}`} className="mega-col">
              <h3 className="flex items-center gap-2">
                <span className="mega-ico">
                  <NavIcon name={col.icon || 'Circle'} className="size-4" />
                </span>
                {col.href ? (
                  <Link href={go(col.href)} onClick={onNavigate}>
                    {col.title}
                  </Link>
                ) : (
                  <span>{col.title}</span>
                )}
              </h3>
              {col.lead ? <p>{col.lead}</p> : null}
              <ul>
                {(col.items ?? []).map((item, ii) => (
                  <li key={`${item.href}-${ii}`}>
                    <Link href={go(item.href)} className="mega-item" onClick={onNavigate}>
                      <span className="mega-ico">
                        <NavIcon name={item.icon || 'Circle'} className="size-3.5" />
                      </span>
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
          {columns.length < 5 && data.promo_title ? (
            <Link href={go(data.cta_href)} className="mega-promo" onClick={onNavigate}>
              <small className="text-xs">{data.promo_small}</small>
              <strong className="mt-3 block text-xl leading-snug">{data.promo_title}</strong>
              <span className="mt-3 flex items-center gap-1 text-sm">
                {data.panel_hint}
                <ArrowUpLeft className="size-4 ltr:-scale-x-100" />
              </span>
            </Link>
          ) : null}
        </div>
        <div className="mega-foot">
          <p>{data.panel_hint}</p>
          {mega.href && data.see_all ? (
            <Link href={go(mega.href)} onClick={onNavigate}>
              {data.see_all}
            </Link>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function MobileNav({
  id,
  data,
  closeLabel,
  onClose,
}: {
  id: string;
  data: HeaderData;
  closeLabel: string;
  onClose: () => void;
}) {
  const [open, setOpen] = useState<number | null>(null);

  return (
    <div className="mobile-nav-root min-[1100px]:hidden" role="dialog" aria-modal="true" id={id}>
      <button type="button" className="mobile-nav-backdrop" aria-label={closeLabel} onClick={onClose} />
      <div className="mobile-sheet">
      <div className="webina-shell pb-10 pt-2">
        {data.nav.map((entry, i) =>
          entry.columns?.length ? (
            <MobileGroup key={`${entry.href}-${i}`} mega={entry} open={open === i} onToggle={() => setOpen((id) => (id === i ? null : i))} onClose={onClose} />
          ) : (
            <Link key={`${entry.href}-${i}`} href={go(entry.href)} onClick={onClose} className="mobile-link">
              {entry.label}
            </Link>
          ),
        )}
        <div className="mt-6 grid gap-2">
          {data.cta_label ? (
            <Link href={go(data.cta_href)} onClick={onClose} className="btn-primary w-full">
              {data.cta_label}
            </Link>
          ) : null}
          {data.mobile_secondary_label ? (
            <Link href={go(data.mobile_secondary_href)} onClick={onClose} className="btn-ghost w-full">
              {data.mobile_secondary_label}
            </Link>
          ) : null}
        </div>
      </div>
      </div>
    </div>
  );
}

function MobileGroup({ mega, open, onToggle, onClose }: { mega: NavItemData; open: boolean; onToggle: () => void; onClose: () => void }) {
  return (
    <div className="mobile-group">
      <button type="button" className="flex w-full items-center justify-between py-4 text-start" aria-expanded={open} onClick={onToggle}>
        {mega.label}
        <ChevronDown className={cn('size-5 text-[var(--brand-primary)] transition', open && 'rotate-180')} />
      </button>
      {open ? (
        <div className="space-y-5 pb-5">
          {(mega.columns ?? []).map((col, ci) => (
            <div key={`${col.title}-${ci}`}>
              {col.href ? (
                <Link href={go(col.href)} onClick={onClose} className="mobile-group-title">
                  {col.title}
                </Link>
              ) : (
                <p className="mobile-group-title">{col.title}</p>
              )}
              <ul className="mt-2 grid grid-cols-1 gap-0.5 sm:grid-cols-2">
                {(col.items ?? []).map((item, ii) => (
                  <li key={`${item.href}-${ii}`}>
                    <Link href={go(item.href)} onClick={onClose} className="mega-item">
                      <span className="mega-ico">
                        <NavIcon name={item.icon || 'Circle'} className="size-3.5" />
                      </span>
                      {item.label}
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
