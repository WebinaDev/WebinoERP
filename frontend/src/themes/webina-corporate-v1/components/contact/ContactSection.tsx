'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowUpLeft, Clock, MapPin, Navigation } from 'lucide-react';
import { siteHref } from '@/lib/public-api-server';
import { usePublicData } from '../../builder/usePublicData';
import { NavIcon } from '../NavIconClient';
import {
  channelIcon,
  contactPageDefaults,
  isExternalHref,
  mergeContent,
  normalizeContact,
  normalizeSocials,
  type ButtonItem,
  type ContactChannel,
  type ContactPageData,
} from '../../site-content';
import { ContactLeadForm, type LeadField } from './ContactLeadForm';
import { CopyButton } from './CopyButton';

type Site = { branding?: Record<string, unknown> | null; social_links?: unknown };

function go(path: string) {
  return isExternalHref(path) || path.startsWith('#') ? path : siteHref(undefined, path.replace(/^\//, ''));
}

export function CtaButtons({ items }: { items: ButtonItem[] }) {
  return (
    <>
      {items
        .filter((b) => b.label && b.href)
        .map((b, i) => {
          const cls = b.style === 'link' ? 'text-link' : b.style === 'ghost' ? 'btn-ghost' : 'btn-primary';
          return isExternalHref(b.href) || b.href.startsWith('#') ? (
            <a key={i} href={b.href} className={cls}>
              {b.label}
            </a>
          ) : (
            <Link key={i} href={go(b.href)} className={cls}>
              {b.label}
            </Link>
          );
        })}
    </>
  );
}

function osmEmbed(lat: number, lng: number, zoom: number) {
  const d = 0.012 * 2 ** (15 - Math.min(19, Math.max(3, zoom)));
  const bbox = [lng - d, lat - d * 0.6, lng + d, lat + d * 0.6].map((n) => n.toFixed(5)).join(',');
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lng}`;
}

function useContactProps(props: Record<string, unknown>): ContactPageData {
  const t = useTranslations();
  const fa = useLocale() === 'fa';
  return fa ? mergeContent(contactPageDefaults(t), props) : contactPageDefaults(t);
}

/** One contact-page section, rendered by the «وبینا» builder widgets. */
export function ContactSection({ id, props }: { id: string; props: Record<string, unknown> }) {
  const p = useContactProps(props);
  switch (id) {
    case 'hero':
      return <Hero p={p} />;
    case 'cards':
      return <Cards p={p} />;
    case 'form':
      return <FormCard p={p} />;
    case 'hours':
      return <Hours p={p} />;
    case 'map':
      return <MapCard p={p} />;
    case 'socials':
      return <Socials p={p} />;
    case 'faq':
      return <Faq p={p} />;
    case 'cta':
      return <CtaBand title={p.cta_title} text={p.cta_text} buttons={p.cta_buttons} />;
    default:
      return null;
  }
}

function useChannels(p: ContactPageData): ContactChannel[] {
  const site = usePublicData<Site>(p.channels.length ? null : '/v1/public/site');
  return p.channels.length ? p.channels : (normalizeContact(site?.branding?.contact)?.items ?? []);
}

function Hero({ p }: { p: ContactPageData }) {
  const featured = useChannels(p).slice(0, 2);
  return (
    <header className="cp-hero">
      <div className="webina-shell cp-hero-grid">
        <div>
          {p.hero_kicker ? <p className="webina-kicker">{p.hero_kicker}</p> : null}
          <h1>{p.hero_title}</h1>
          {p.hero_lead ? <p className="lead">{p.hero_lead}</p> : null}
          <div className="hero-actions mt-8">
            <CtaButtons items={p.hero_buttons} />
          </div>
        </div>
        {featured.length ? (
          <ul className="cp-hero-lines" aria-label={p.channels_title}>
            {featured.map((ch, i) => (
              <li key={i}>
                <a href={ch.href || '#contact-channels'}>
                  <span className="cp-ico">
                    <NavIcon name={channelIcon(ch)} className="size-5" />
                  </span>
                  <span>
                    <small>{ch.label}</small>
                    <strong dir="ltr">{ch.value}</strong>
                  </span>
                  <ArrowUpLeft className="size-5 ltr:-scale-x-100" aria-hidden />
                </a>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </header>
  );
}

function Cards({ p }: { p: ContactPageData }) {
  const channels = useChannels(p);
  if (!channels.length) return null;
  return (
    <section id="contact-channels" className="section scroll-mt-24">
      <div className="webina-shell">
        <div className="section-head">
          <div>
            <h2>{p.channels_title}</h2>
            {p.channels_lead ? <p className="mt-3">{p.channels_lead}</p> : null}
          </div>
        </div>
        <div className="cc-grid">
          {channels.map((ch, i) => (
            <article key={`${ch.value}-${i}`} className="cc-card">
              <div className="cc-top">
                <span className="cp-ico">
                  <NavIcon name={channelIcon(ch)} className="size-5" />
                </span>
                <CopyButton value={ch.value} label={p.copy_label} copiedLabel={p.copied_label} />
              </div>
              <h3>{ch.label}</h3>
              <p className="cc-value" dir="ltr">
                {ch.value}
              </p>
              {ch.note ? <p className="cc-note">{ch.note}</p> : null}
              {ch.href ? (
                <a
                  className="cc-action"
                  href={ch.href}
                  target={ch.href.startsWith('http') ? '_blank' : undefined}
                  rel={ch.href.startsWith('http') ? 'noreferrer' : undefined}
                >
                  {ch.action_label || ch.label}
                  <ArrowUpLeft className="size-4 ltr:-scale-x-100" aria-hidden />
                </a>
              ) : null}
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

function FormCard({ p }: { p: ContactPageData }) {
  const t = useTranslations();
  const form = usePublicData<{ fields?: LeadField[]; success_message?: string | null }>(
    p.form_enabled && p.form_slug ? `/v1/public/forms/${encodeURIComponent(p.form_slug)}` : null,
  );
  if (!p.form_enabled) return null;
  const fields = (form?.fields ?? []).filter((f) => f && f.name);
  return (
    <div id="contact-form" className="cp-form-card scroll-mt-24">
      <h2>{p.form_title}</h2>
      {p.form_lead ? <p className="muted mt-3 leading-8">{p.form_lead}</p> : null}
      {fields.length ? (
        <ContactLeadForm
          slug={p.form_slug}
          fields={fields}
          copy={{
            submit: p.submit_label,
            successTitle: p.success_title,
            successText: p.success_text || form?.success_message || '',
            again: p.again_label,
            privacy: p.privacy_note,
          }}
        />
      ) : (
        <p className="empty-note mt-6">{form ? t('site.contactPage.formUnavailable') : '…'}</p>
      )}
    </div>
  );
}

function Hours({ p }: { p: ContactPageData }) {
  return (
    <div className="cp-panel">
      <h2 className="cp-panel-title">
        <Clock className="size-5" aria-hidden />
        {p.hours_title}
      </h2>
      {p.hours.length ? (
        <dl className="cp-hours">
          {p.hours.map((h, i) => (
            <div key={i}>
              <dt>{h.days}</dt>
              <dd>{h.time}</dd>
            </div>
          ))}
        </dl>
      ) : (
        <p className="muted mt-3 text-sm leading-7">{p.hours_empty}</p>
      )}
      {p.hours_note ? <p className="cp-hours-note">{p.hours_note}</p> : null}
    </div>
  );
}

function MapCard({ p }: { p: ContactPageData }) {
  const t = useTranslations();
  if (!p.map_enabled) return null;
  const lat = Number.parseFloat(p.map_lat);
  const lng = Number.parseFloat(p.map_lng);
  const hasCoords = Number.isFinite(lat) && Number.isFinite(lng);
  const zoom = Number(p.map_zoom) || 15;
  const mapSrc = p.map_embed_url || (hasCoords ? osmEmbed(lat, lng, zoom) : '');
  const mapLinks = p.map_links.filter((l) => l.label && l.href);
  const directions = p.map_directions_url || (hasCoords ? `https://balad.ir/location?latitude=${lat}&longitude=${lng}&zoom=${zoom}` : '');
  return (
    <div className="cp-panel cp-map">
      <h2 className="cp-panel-title">
        <MapPin className="size-5" aria-hidden />
        {p.map_title}
      </h2>
      {mapSrc ? (
        <div className="cp-map-frame">
          <iframe src={mapSrc} title={p.map_title} loading="lazy" referrerPolicy="no-referrer-when-downgrade" />
        </div>
      ) : (
        <div className="cp-map-empty">
          <svg viewBox="0 0 320 160" aria-hidden className="cp-map-art">
            <path d="M0 40 H320 M0 100 H320 M60 0 V160 M180 0 V160 M260 0 V160" stroke="rgba(0,84,255,.14)" strokeWidth="10" />
            <path d="M0 140 C 90 120 140 60 320 70" stroke="rgba(0,84,255,.22)" strokeWidth="6" fill="none" strokeDasharray="10 8" />
            <circle cx="180" cy="74" r="16" fill="#0054ff" opacity=".18" />
            <circle cx="180" cy="74" r="7" fill="#0054ff" />
          </svg>
          <p>{p.map_empty_text}</p>
        </div>
      )}
      {p.map_address ? <p className="cp-address">{p.map_address}</p> : null}
      {mapLinks.length ? (
        <div className="cp-map-links">
          {mapLinks.map((l, i) => (
            <a key={i} href={l.href} target="_blank" rel="noreferrer">
              <Navigation className="size-4" aria-hidden />
              {l.label}
            </a>
          ))}
        </div>
      ) : directions ? (
        <a className="text-link mt-3" href={directions} target="_blank" rel="noreferrer">
          <Navigation className="size-4" aria-hidden />
          {t('site.contactPage.directions')}
        </a>
      ) : null}
    </div>
  );
}

function Socials({ p }: { p: ContactPageData }) {
  const site = usePublicData<Site>('/v1/public/site');
  const socials = normalizeSocials(site?.social_links ?? null);
  if (!socials.length) return null;
  return (
    <section className="section">
      <div className="webina-shell">
        <div className="section-head">
          <div>
            <h2>{p.social_title}</h2>
            {p.social_lead ? <p className="mt-3">{p.social_lead}</p> : null}
          </div>
        </div>
        <div className="cs-grid">
          {socials.map((s) => (
            <a key={s.href} href={s.href} target="_blank" rel="noreferrer" className="cs-card">
              <span className="cp-ico">
                <NavIcon name={s.icon || channelIcon({ href: s.href })} className="size-5" />
              </span>
              <span className="min-w-0">
                <strong>{s.label}</strong>
                {s.handle ? (
                  <small dir="ltr" className="block truncate">
                    {s.handle}
                  </small>
                ) : null}
              </span>
              <ArrowUpLeft className="cs-arrow size-4 ltr:-scale-x-100" aria-hidden />
            </a>
          ))}
        </div>
      </div>
    </section>
  );
}

function Faq({ p }: { p: ContactPageData }) {
  const own = p.faq_items.filter((f) => f.question);
  const remote = usePublicData<{ question: string; answer: string }[]>(p.faq_enabled && !own.length ? '/v1/public/faq' : null);
  if (!p.faq_enabled) return null;
  const faq = (own.length ? own : (remote ?? [])).slice(0, Math.max(1, Number(p.faq_limit) || 4));
  if (!faq.length) return null;
  return (
    <section className="section">
      <div className="webina-shell cp-faq">
        <h2>{p.faq_title}</h2>
        <div>
          {faq.map((f, i) => (
            <details key={i} className="cp-faq-item" open={i === 0}>
              <summary>{f.question}</summary>
              <p>{f.answer}</p>
            </details>
          ))}
        </div>
      </div>
    </section>
  );
}

export function CtaBand({ title, text, buttons }: { title: string; text?: string; buttons: ButtonItem[] }) {
  if (!title) return null;
  return (
    <section className="cta-bar">
      <div className="webina-shell cta-bar-inner">
        <div>
          <h2>{title}</h2>
          {text ? <p>{text}</p> : null}
        </div>
        <div className="flex flex-wrap gap-3">
          <CtaButtons items={buttons} />
        </div>
      </div>
    </section>
  );
}
