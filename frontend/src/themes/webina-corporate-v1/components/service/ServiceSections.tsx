'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { ArrowUpLeft, Check, ChevronDown, Quote, X } from 'lucide-react';
import { siteHref } from '@/lib/public-api-server';
import { toPersianDigits } from '@/lib/locale/calendar-date';
import { cn } from '@/lib/utils';
import type { ServiceProps, ServiceType } from '../../service-content';
import { isExternalHref, type ButtonItem } from '../../site-content';
import { NavIcon } from '../NavIconClient';

function go(path: string) {
  if (!path) return '#';
  return isExternalHref(path) ? path : siteHref(undefined, path.replace(/^\//, ''));
}

function Buttons({ items, dark }: { items: ButtonItem[]; dark?: boolean }) {
  return (
    <>
      {(items || [])
        .filter((b) => b.label && b.href)
        .map((b, i) => {
          const cls = b.style === 'link' ? 'text-link' : b.style === 'ghost' ? (dark ? 'btn-ghost-dark' : 'btn-ghost') : 'btn-primary';
          return (
            <Link key={i} href={go(b.href)} className={cls}>
              {b.label}
              {b.style === 'primary' ? <ArrowUpLeft className="size-4 ltr:-scale-x-100" aria-hidden /> : null}
            </Link>
          );
        })}
    </>
  );
}

function asList<T>(v: unknown): T[] {
  return Array.isArray(v) ? (v as T[]) : [];
}

export function ServiceSection({ type, props }: { type: ServiceType; props: Record<string, unknown> }) {
  const p = props as unknown as ServiceProps[ServiceType];
  switch (type) {
    case 'hero':
      return <Hero p={p as ServiceProps['hero']} />;
    case 'problem':
      return <Problem p={p as ServiceProps['problem']} />;
    case 'define':
      return <Define p={p as ServiceProps['define']} />;
    case 'audience':
      return <Audience p={p as ServiceProps['audience']} />;
    case 'deliverables':
      return <Deliverables p={p as ServiceProps['deliverables']} />;
    case 'process':
      return <Process p={p as ServiceProps['process']} />;
    case 'compare':
      return <Compare p={p as ServiceProps['compare']} />;
    case 'portfolio':
      return <Portfolio p={p as ServiceProps['portfolio']} />;
    case 'trust':
      return <Trust p={p as ServiceProps['trust']} />;
    case 'faq':
      return <Faq p={p as ServiceProps['faq']} />;
    case 'cta':
      return <Cta p={p as ServiceProps['cta']} />;
    case 'exclude':
      return <Exclude p={p as ServiceProps['exclude']} />;
    case 'quote':
      return <MidQuote p={p as ServiceProps['quote']} />;
    default:
      return null;
  }
}

function Hero({ p }: { p: ServiceProps['hero'] }) {
  const tags = asList<{ text: string }>(p.tags);
  return (
    <>
      <header className="ab-hero svc-hero">
        <div className="ab-hero-texture" aria-hidden />
        <div className="webina-shell ab-hero-grid">
          <div>
            {p.kicker ? <p className="ab-kicker">{p.kicker}</p> : null}
            {p.title ? <h1>{p.title}</h1> : null}
            <span className="ab-underline" aria-hidden />
            {p.subtitle ? <p className="svc-hero-sub">{p.subtitle}</p> : null}
            {p.text ? <p className="ab-hero-text">{p.text}</p> : null}
            <div className="mt-9 flex flex-wrap gap-3">
              <Buttons items={asList(p.buttons)} dark />
            </div>
          </div>
          <div className="ab-hero-art" aria-hidden>
            <svg viewBox="0 0 420 340" className="w-full">
              <rect x="28" y="28" width="364" height="284" rx="24" fill="rgba(255,255,255,.04)" stroke="rgba(255,255,255,.12)" />
              <rect x="52" y="56" width="200" height="14" rx="7" fill="rgba(255,255,255,.2)" />
              <rect x="52" y="84" width="140" height="10" rx="5" fill="rgba(255,255,255,.1)" />
              <rect x="52" y="120" width="316" height="120" rx="16" fill="rgba(0,84,255,.18)" stroke="rgba(77,139,255,.45)" />
              <rect x="72" y="140" width="90" height="80" rx="10" fill="rgba(255,255,255,.08)" />
              <rect x="176" y="140" width="170" height="18" rx="6" fill="rgba(255,255,255,.14)" />
              <rect x="176" y="170" width="140" height="12" rx="5" fill="rgba(255,255,255,.08)" />
              <rect x="176" y="194" width="120" height="12" rx="5" fill="rgba(255,255,255,.08)" />
              <circle cx="340" cy="260" r="28" fill="#0054ff" opacity=".9" />
            </svg>
            {tags.map((tag, i) => (
              <span key={i} className={cn('ab-tag', `t-${(i % 3) + 1}`)}>
                {tag.text}
              </span>
            ))}
          </div>
        </div>
      </header>
      {p.dock_cta_label && p.dock_cta_href ? (
        <div className="svc-dock md:hidden">
          <span>{p.dock_label || p.title}</span>
          <Link href={go(p.dock_cta_href)} className="btn-primary">
            {p.dock_cta_label}
          </Link>
        </div>
      ) : null}
    </>
  );
}

function Problem({ p }: { p: ServiceProps['problem'] }) {
  const pains = asList<{ text: string }>(p.pains).filter((x) => x.text);
  const before = asList<{ text: string }>(p.before_items).filter((x) => x.text);
  const after = asList<{ text: string }>(p.after_items).filter((x) => x.text);
  return (
    <section className="section">
      <div className="webina-shell svc-narrow">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        {p.lead ? <p className="ab-lead">{p.lead}</p> : null}
        {pains.length ? (
          <ul className="svc-pain-list">
            {pains.map((item, i) => (
              <li key={i}>
                <X className="size-4 text-red-500" aria-hidden />
                <span>{item.text}</span>
              </li>
            ))}
          </ul>
        ) : null}
        {p.result ? <p className="svc-result">{p.result}</p> : null}
        {p.closing ? <p className="ab-lead font-bold text-[var(--ink,#22242a)]">{p.closing}</p> : null}
        {(before.length || after.length) ? (
          <div className="svc-ba">
            <div className="svc-ba-card is-before">
              <h3>{p.before_label || 'قبل'}</h3>
              <ul>
                {before.map((item, i) => (
                  <li key={i}>{item.text}</li>
                ))}
              </ul>
            </div>
            <div className="svc-ba-card is-after">
              <h3>{p.after_label || 'بعد'}</h3>
              <ul>
                {after.map((item, i) => (
                  <li key={i}>{item.text}</li>
                ))}
              </ul>
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}

function Define({ p }: { p: ServiceProps['define'] }) {
  const cards = asList<{ title: string; text: string; icon?: string }>(p.cards);
  return (
    <section className="section svc-alt">
      <div className="webina-shell">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        {p.body ? <p className="ab-lead whitespace-pre-wrap">{p.body}</p> : null}
        {p.closing ? <p className="mt-4 text-[1.05rem] leading-8 text-[var(--muted)]">{p.closing}</p> : null}
        <div className="svc-feature-grid">
          {cards.map((c, i) => (
            <article key={i} className="svc-feature">
              <span className="ab-ico lg">
                <NavIcon name={c.icon || 'Sparkles'} className="size-5" />
              </span>
              <h3>{c.title}</h3>
              {c.text ? <p>{c.text}</p> : null}
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

function Audience({ p }: { p: ServiceProps['audience'] }) {
  const items = asList<{ text: string }>(p.items).filter((x) => x.text);
  return (
    <section className="section">
      <div className="webina-shell svc-narrow">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        <ul className="svc-check-grid">
          {items.map((item, i) => (
            <li key={i}>
              <Check className="size-4 text-[var(--brand-primary,#0054ff)]" aria-hidden />
              <span>{item.text}</span>
            </li>
          ))}
        </ul>
        {p.soft_no ? <p className="svc-soft-no">{p.soft_no}</p> : null}
        {p.closing ? <p className="ab-lead font-bold text-[var(--ink,#22242a)]">{p.closing}</p> : null}
      </div>
    </section>
  );
}

function Deliverables({ p }: { p: ServiceProps['deliverables'] }) {
  const items = asList<{ text: string }>(p.items).filter((x) => x.text);
  return (
    <section className="section svc-alt">
      <div className="webina-shell">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        <ol className="svc-deliver">
          {items.map((item, i) => (
            <li key={i}>
              <span className="svc-num">{toPersianDigits(String(i + 1).padStart(2, '0'))}</span>
              <span>{item.text}</span>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}

function Process({ p }: { p: ServiceProps['process'] }) {
  const steps = asList<{ title: string; text: string }>(p.steps);
  return (
    <section className="section">
      <div className="webina-shell">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        <ol className="svc-process">
          {steps.map((step, i) => (
            <li key={i}>
              <span className="svc-process-num">{toPersianDigits(String(i + 1))}</span>
              <div>
                <h3>{step.title}</h3>
                {step.text ? <p>{step.text}</p> : null}
              </div>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}

function Compare({ p }: { p: ServiceProps['compare'] }) {
  const rows = asList<{ left: string; right: string }>(p.rows);
  return (
    <section className="section svc-alt">
      <div className="webina-shell">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        <div className="svc-compare" role="table" aria-label={p.title}>
          <div className="svc-compare-head" role="row">
            <div role="columnheader">{p.left_title || 'قالب آماده'}</div>
            <div role="columnheader">{p.right_title || 'طراحی اختصاصی'}</div>
          </div>
          {rows.map((row, i) => (
            <div key={i} className="svc-compare-row" role="row">
              <div role="cell">
                <X className="size-4 text-red-400" aria-hidden />
                <span>{row.left}</span>
              </div>
              <div role="cell">
                <Check className="size-4 text-[var(--brand-primary,#0054ff)]" aria-hidden />
                <span>{row.right}</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function Portfolio({ p }: { p: ServiceProps['portfolio'] }) {
  const items = asList<{ title: string; href?: string; image?: string; tag?: string }>(p.items).filter((x) => x.title);
  return (
    <section className="section">
      <div className="webina-shell">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        {items.length ? (
          <div className="svc-port-grid">
            {items.map((item, i) => {
              const inner = (
                <>
                  <div className="svc-port-img" style={item.image ? { backgroundImage: `url(${item.image})` } : undefined} />
                  <div className="svc-port-meta">
                    {item.tag ? <span>{item.tag}</span> : null}
                    <strong>{item.title}</strong>
                  </div>
                </>
              );
              return item.href ? (
                <Link key={i} href={go(item.href)} className="svc-port-card">
                  {inner}
                </Link>
              ) : (
                <div key={i} className="svc-port-card">
                  {inner}
                </div>
              );
            })}
          </div>
        ) : (
          <div className="svc-port-empty">
            <p>{p.empty || 'به‌زودی'}</p>
            {p.cta_label && p.cta_href ? (
              <Link href={go(p.cta_href)} className="btn-ghost">
                {p.cta_label}
              </Link>
            ) : null}
          </div>
        )}
      </div>
    </section>
  );
}

function Trust({ p }: { p: ServiceProps['trust'] }) {
  const items = asList<{ title: string; text: string; icon?: string }>(p.items);
  return (
    <section className="section svc-alt">
      <div className="webina-shell">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        <div className="svc-feature-grid">
          {items.map((c, i) => (
            <article key={i} className="svc-feature">
              <span className="ab-ico">
                <NavIcon name={c.icon || 'ShieldCheck'} className="size-4" />
              </span>
              <h3>{c.title}</h3>
              {c.text ? <p>{c.text}</p> : null}
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

function Faq({ p }: { p: ServiceProps['faq'] }) {
  const items = asList<{ q: string; a: string }>(p.items).filter((x) => x.q);
  const [open, setOpen] = useState<number | null>(0);
  const json = useMemo(
    () =>
      items.length
        ? JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'FAQPage',
            mainEntity: items.map((i) => ({
              '@type': 'Question',
              name: i.q,
              acceptedAnswer: { '@type': 'Answer', text: i.a || '' },
            })),
          }).replace(/</g, '\\u003c')
        : '',
    [items],
  );
  if (!items.length) return null;
  return (
    <section className="section">
      <div className="webina-shell svc-narrow">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        <div className="svc-faq">
          {items.map((item, i) => {
            const isOpen = open === i;
            return (
              <div key={i} className={cn('svc-faq-item', isOpen && 'is-open')}>
                <button type="button" aria-expanded={isOpen} onClick={() => setOpen(isOpen ? null : i)}>
                  <span>{item.q}</span>
                  <ChevronDown className="size-4 shrink-0" aria-hidden />
                </button>
                {isOpen ? <div className="svc-faq-a">{item.a}</div> : null}
              </div>
            );
          })}
        </div>
        {json ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: json }} /> : null}
      </div>
    </section>
  );
}

function Cta({ p }: { p: ServiceProps['cta'] }) {
  return (
    <section className="ab-cta svc-cta">
      <div className="webina-shell ab-cta-inner">
        {p.title ? <h2>{p.title}</h2> : null}
        {p.text ? <p>{p.text}</p> : null}
        <div className="mt-8 flex flex-wrap gap-3">
          <Buttons items={asList(p.buttons)} dark />
        </div>
      </div>
    </section>
  );
}

function Exclude({ p }: { p: ServiceProps['exclude'] }) {
  const items = asList<{ text: string }>(p.items).filter((x) => x.text);
  if (!items.length && !p.title) return null;
  return (
    <section className="section">
      <div className="webina-shell svc-narrow">
        {p.title ? <h2 className="ab-h2">{p.title}</h2> : null}
        <ul className="svc-pain-list">
          {items.map((item, i) => (
            <li key={i}>
              <X className="size-4 text-[var(--muted)]" aria-hidden />
              <span>{item.text}</span>
            </li>
          ))}
        </ul>
        {p.note ? <p className="ab-lead">{p.note}</p> : null}
      </div>
    </section>
  );
}

function MidQuote({ p }: { p: ServiceProps['quote'] }) {
  if (!p.text) return null;
  return (
    <section className="section svc-quote-wrap">
      <div className="webina-shell svc-narrow">
        <blockquote className="svc-quote">
          <Quote className="size-8 text-[var(--brand-primary,#0054ff)]" aria-hidden />
          <p>{p.text}</p>
          {p.attribution ? <cite>{p.attribution}</cite> : null}
        </blockquote>
      </div>
    </section>
  );
}
