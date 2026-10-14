import { routeMetadata } from '@/lib/seo';
import Link from 'next/link';
import { getLocale, getTranslations } from 'next-intl/server';
import { ArrowUpLeft, Clock, MapPin, Navigation } from 'lucide-react';
import { apiServer, getPublicSite, siteHref } from '@/lib/public-api-server';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

import { ContactLeadForm, type LeadField } from '@/themes/webina-corporate-v1/components/contact/ContactLeadForm';
import { CopyButton } from '@/themes/webina-corporate-v1/components/contact/CopyButton';
import { NavIcon } from '@/themes/webina-corporate-v1/components/NavIcon';
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
} from '@/themes/webina-corporate-v1/site-content';

export const revalidate = 60;

function go(path: string) {
  return isExternalHref(path) ? path : siteHref(undefined, path.replace(/^\//, ''));
}

function Buttons({ items }: { items: ButtonItem[] }) {
  return (
    <>
      {items
        .filter((b) => b.label && b.href)
        .map((b, i) => {
          const cls = b.style === 'link' ? 'text-link' : b.style === 'ghost' ? 'btn-ghost' : 'btn-primary';
          return isExternalHref(b.href) ? (
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

export function generateMetadata() {
  return routeMetadata('/contact', { title: 'تماس با ما' });
}

export default async function ContactPage() {
  const t = await getTranslations();
  const locale = await getLocale();
  const fa = locale === 'fa';

  // Built in the page builder («وبینا» widgets). The code below is the fallback before a document exists.
  const published = fa ? await loadPublishedPage('contact') : null;
  if (published) return <StorefrontDocument document={published} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(published) }} />;

  let branding: Record<string, unknown> = {};
  let socialsRaw: unknown = null;
  try {
    const res = await getPublicSite();
    branding = (res.data.branding as Record<string, unknown> | null) ?? {};
    socialsRaw = res.data.social_links ?? null;
  } catch {
    /* defaults below */
  }

  const p: ContactPageData = fa ? mergeContent(contactPageDefaults(t), branding.contact_page) : contactPageDefaults(t);
  const reach = normalizeContact(branding.contact);
  const socials = normalizeSocials(socialsRaw);
  const channels: ContactChannel[] = p.channels.length ? p.channels : (reach?.items ?? []);

  let fields: LeadField[] = [];
  let successMessage = '';
  if (p.form_enabled && p.form_slug) {
    try {
      const res = await apiServer<{ data: { fields?: LeadField[]; success_message?: string | null } }>(`/v1/public/forms/${encodeURIComponent(p.form_slug)}`);
      fields = (res.data.fields ?? []).filter((f) => f && f.name);
      successMessage = res.data.success_message ?? '';
    } catch {
      fields = [];
    }
  }

  let faq = p.faq_items.filter((f) => f.question);
  if (p.faq_enabled && !faq.length) {
    try {
      const res = await apiServer<{ data: { question: string; answer: string }[] }>('/v1/public/faq');
      faq = res.data ?? [];
    } catch {
      faq = [];
    }
  }
  faq = faq.slice(0, Math.max(1, Number(p.faq_limit) || 4));

  const lat = Number.parseFloat(p.map_lat);
  const lng = Number.parseFloat(p.map_lng);
  const hasCoords = Number.isFinite(lat) && Number.isFinite(lng);
  const mapSrc = p.map_embed_url || (hasCoords ? osmEmbed(lat, lng, Number(p.map_zoom) || 15) : '');
  const mapLinks = p.map_links.filter((l) => l.label && l.href);
  const directions = p.map_directions_url || (hasCoords ? `https://balad.ir/location?latitude=${lat}&longitude=${lng}&zoom=${Number(p.map_zoom) || 15}` : '');
  const featured = channels.slice(0, 2);

  return (
    <div className="contact-page">
      <header className="cp-hero">
        <div className="webina-shell cp-hero-grid">
          <div>
            {p.hero_kicker ? <p className="webina-kicker">{p.hero_kicker}</p> : null}
            <h1>{p.hero_title}</h1>
            {p.hero_lead ? <p className="lead">{p.hero_lead}</p> : null}
            <div className="hero-actions mt-8">
              <Buttons items={p.hero_buttons} />
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

      {channels.length ? (
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
      ) : null}

      <section className="section cp-split-wrap">
        <div className="webina-shell cp-split">
          {p.form_enabled ? (
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
                    successText: p.success_text || successMessage,
                    again: p.again_label,
                    privacy: p.privacy_note,
                  }}
                />
              ) : (
                <p className="empty-note mt-6">{t('site.contactPage.formUnavailable')}</p>
              )}
            </div>
          ) : null}

          <aside className="cp-aside">
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

            {p.map_enabled ? (
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
            ) : null}
          </aside>
        </div>
      </section>

      {socials.length ? (
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
      ) : null}

      {p.faq_enabled && faq.length ? (
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
      ) : null}

      {p.cta_title ? (
        <section className="cta-bar">
          <div className="webina-shell cta-bar-inner">
            <div>
              <h2>{p.cta_title}</h2>
              {p.cta_text ? <p>{p.cta_text}</p> : null}
            </div>
            <div className="flex flex-wrap gap-3">
              <Buttons items={p.cta_buttons} />
            </div>
          </div>
        </section>
      ) : null}
    </div>
  );
}
