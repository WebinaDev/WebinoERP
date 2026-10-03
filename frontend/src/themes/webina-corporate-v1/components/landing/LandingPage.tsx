'use client';

import Link from 'next/link';
import { useRef } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { siteHref } from '@/lib/public-api-server';
import { PRODUCTS, copyFor } from '../../catalog';
import { INDUSTRY_COUNT, SERVICE_COUNT, SERVICE_MEGA, SOLUTION_MEGA } from '../../site-nav';
import { NavIcon } from '../NavIcon';
import { useLandingMotion } from './useLandingMotion';

export type LandingData = {
  site?: { name?: string; branding?: Record<string, unknown> | null };
  blocks?: { type: string; enabled?: boolean }[];
  testimonials?: { id: number; author: string; quote: string; company?: string | null; role?: string | null }[];
  portfolio?: { id: number; slug: string; title: string; description?: string | null }[];
  blog?: { id: number; slug: string; title: string; excerpt?: string | null }[];
  announcements?: { id: number; title: string; body?: string | null }[];
  faq?: { id: number; question: string; answer: string }[];
} | null;

const PRODUCT_IDS = new Set(['webino-dashboard', 'webino-erp', 'webino-docs', 'webino-hosting']);

function show(blocks: { type: string; enabled?: boolean }[] | undefined, type: string) {
  if (!blocks?.length) return true;
  const row = blocks.find((b) => b.type === type);
  if (!row) return true;
  return row.enabled !== false;
}

export function LandingPage({ data }: { data: LandingData }) {
  const t = useTranslations();
  const locale = useLocale();
  const root = useRef<HTMLElement>(null);
  useLandingMotion(root, locale);
  const name = data?.site?.name ?? t('site.home.defaultName');
  const blocks = data?.blocks;
  const portfolio = (data?.portfolio ?? []).filter((item) => !PRODUCT_IDS.has(item.slug));
  const quotes = data?.testimonials ?? [];
  const posts = data?.blog ?? [];
  const faq = data?.faq ?? [];
  const news = data?.announcements ?? [];

  const stats = [
    { key: 'pillars', n: SERVICE_MEGA.columns.length },
    { key: 'services', n: SERVICE_COUNT },
    { key: 'industries', n: INDUSTRY_COUNT },
    { key: 'products', n: PRODUCTS.length },
  ] as const;

  const fallbackFaq = [1, 2, 3, 4, 5].map((n) => ({
    id: n,
    question: t(`site.landing.faq.q${n}`),
    answer: t(`site.landing.faq.a${n}`),
  }));

  return (
    <article ref={root} className="webina-landing">
      {show(blocks, 'hero') ? (
        <section className="webina-shell hero">
          <div>
            <p className="webina-kicker hero-kicker">{t('site.landing.kicker')}</p>
            <h1 className="hero-title">
              {t('site.landing.heroTitle').split(' ').map((word, i) => (
                <span key={`${word}-${i}`} className="me-[0.28em] inline-block">
                  {word}
                </span>
              ))}
            </h1>
            <p className="lead hero-lead">{t('site.landing.heroLead', { name })}</p>
            <div className="hero-actions hero-cta">
              <Link href={siteHref(undefined, 'consultation')} className="btn-saffron">
                {t('site.landing.ctaConsult')}
              </Link>
              <Link href={siteHref(undefined, 'services')} className="btn-ghost">
                {t('site.home.allServices')}
              </Link>
              <Link href={siteHref(undefined, 'products')} className="btn-ghost">
                {t('site.landing.ctaProducts')}
              </Link>
            </div>
            <div className="stack-fallback">
              {PRODUCTS.map((product) => (
                <Link key={product.id} href={product.href} className="panel">
                  <strong>{copyFor(locale, product.title)}</strong>
                </Link>
              ))}
            </div>
          </div>
          <div className="stack-art" aria-hidden>
            {PRODUCTS.map((product, i) => (
              <article key={product.id} className={`stack-card c${i + 1} ${i % 2 ? 'float-b' : 'float-a'}`}>
                <span className="mega-ico">
                  <NavIcon name={product.icon} className="size-4" />
                </span>
                <strong>{copyFor(locale, product.title)}</strong>
              </article>
            ))}
          </div>
        </section>
      ) : null}

      <div className="ribbon" aria-hidden>
        <div className="ribbon-track">
          {[0, 1].map((copy) => (
            <span key={copy} className="flex gap-6">
              {SERVICE_MEGA.columns.flatMap((col) => col.items).map((item) => (
                <span key={`${copy}-${item.href}`}>{t(item.labelKey)} ·</span>
              ))}
            </span>
          ))}
        </div>
      </div>

      {show(blocks, 'stats') ? (
        <section className="webina-shell">
          <div className="stat-band">
            {stats.map((s) => (
              <div key={s.key} className="reveal">
                <strong>
                  <span data-count={s.n}>0</span>
                </strong>
                <p>{t(`site.landing.stat.${s.key}`)}</p>
              </div>
            ))}
          </div>
          <p className="muted mt-3 text-sm">{t('site.landing.statNote')}</p>
        </section>
      ) : null}

      {show(blocks, 'services') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <h2>{t('site.landing.servicesTitle')}</h2>
                <p className="mt-2">{t('site.landing.servicesLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'services')} className="reveal text-sm font-bold text-[var(--saffron-deep)]">
                {t('site.home.allServices')}
              </Link>
            </div>
            <div className="card-grid cols-5">
              {SERVICE_MEGA.columns.map((col) => (
                <article key={col.titleKey} className="panel reveal">
                  <span className="mega-ico">
                    <NavIcon name={col.icon} />
                  </span>
                  <h3 className="mt-3">
                    <Link href={siteHref(undefined, col.href || 'services')}>{t(col.titleKey)}</Link>
                  </h3>
                  <p className="muted mt-1 text-xs">{t(col.leadKey)}</p>
                  <ul>
                    {col.items.map((item) => (
                      <li key={item.href}>
                        <Link href={siteHref(undefined, item.href)}>{t(item.labelKey)}</Link>
                      </li>
                    ))}
                  </ul>
                </article>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'products') ? (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <h2>{t('site.landing.productsTitle')}</h2>
                <p className="mt-2">{t('site.landing.productsLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'products')} className="text-sm font-bold text-[var(--saffron-deep)]">
                {t('site.nav.products')}
              </Link>
            </div>
            <div className="card-grid cols-2">
              {PRODUCTS.map((product) => (
                <Link key={product.id} href={product.href} className="panel reveal flex gap-3">
                  <span className="mega-ico size-10">
                    <NavIcon name={product.icon} />
                  </span>
                  <span>
                    <h3>{copyFor(locale, product.title)}</h3>
                    <p className="muted mt-1 text-sm">{copyFor(locale, product.body)}</p>
                  </span>
                </Link>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'solutions') ? (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="webina-shell ink-section">
            <div className="section-head">
              <div className="reveal">
                <h2>{t('site.landing.solutionsTitle')}</h2>
                <p className="muted mt-2">{t('site.landing.solutionsLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'solutions')} className="text-sm font-bold" style={{ color: 'var(--saffron)' }}>
                {t('site.nav.solutions')}
              </Link>
            </div>
            <div className="card-grid cols-5">
              {SOLUTION_MEGA.columns.map((col) => (
                <article key={col.titleKey} className="reveal rounded-2xl p-4" style={{ background: 'rgba(246,241,232,.05)' }}>
                  <h3>
                    <Link href={siteHref(undefined, col.href || 'solutions')}>{t(col.titleKey)}</Link>
                  </h3>
                  <ul className="mt-3 space-y-1 text-sm" style={{ color: 'rgba(246,241,232,.75)' }}>
                    {col.items.map((item) => (
                      <li key={item.href}>
                        <Link href={siteHref(undefined, item.href)}>{t(item.labelKey)}</Link>
                      </li>
                    ))}
                  </ul>
                </article>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'process') ? (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="webina-shell">
            <div className="section-head reveal">
              <h2>{t('site.landing.processTitle')}</h2>
              <p>{t('site.landing.processLead')}</p>
            </div>
            <ol className="process">
              {(['m1', 'm2', 'm3', 'ongoing'] as const).map((id, i) => (
                <li key={id} className="panel reveal">
                  <span className="num">{String(i + 1).padStart(2, '0')}</span>
                  <h3 className="mt-2">{t(`site.landing.process.${id}Title`)}</h3>
                  <p className="muted mt-2 text-sm">{t(`site.landing.process.${id}Body`)}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>
      ) : null}

      {show(blocks, 'portfolio_teaser') ? (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <h2>{t('site.home.portfolio')}</h2>
                <p className="mt-2">{t('site.landing.workLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'portfolio')} className="text-sm font-bold text-[var(--saffron-deep)]">
                {t('site.home.all')}
              </Link>
            </div>
            {portfolio.length ? (
              <div className="card-grid cols-3">
                {portfolio.slice(0, 6).map((item) => (
                  <Link key={item.id} href={siteHref(undefined, `portfolio/${item.slug}`)} className="panel reveal">
                    <div className="mb-3 h-24 rounded-xl" style={{ background: 'linear-gradient(135deg,#16130f,#8d6a24)' }} />
                    <h3>{item.title}</h3>
                    {item.description ? <p className="muted mt-2 line-clamp-3 text-sm">{item.description}</p> : null}
                  </Link>
                ))}
              </div>
            ) : (
              <p className="empty-note reveal mt-6">{t('site.landing.portfolioEmpty')}</p>
            )}
          </div>
        </section>
      ) : null}

      {show(blocks, 'testimonials') ? (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="webina-shell">
            <div className="section-head reveal">
              <h2>{quotes.length ? t('site.home.testimonials') : t('site.landing.principlesTitle')}</h2>
              <p>{quotes.length ? t('site.landing.quotesLead') : t('site.landing.principlesLead')}</p>
            </div>
            {quotes.length ? (
              <div className="card-grid cols-3">
                {quotes.slice(0, 3).map((q) => (
                  <blockquote key={q.id} className="panel reveal text-sm leading-7">
                    «{q.quote}»
                    <footer className="mt-4 font-bold">
                      {q.author}
                      {q.company ? <span className="mt-1 block text-xs font-normal text-[var(--muted)]">{q.company}</span> : null}
                    </footer>
                  </blockquote>
                ))}
              </div>
            ) : (
              <div className="card-grid cols-3">
                {(['p1', 'p2', 'p3'] as const).map((id) => (
                  <article key={id} className="panel reveal">
                    <h3>{t(`site.landing.principle.${id}Title`)}</h3>
                    <p className="muted mt-2 text-sm">{t(`site.landing.principle.${id}Body`)}</p>
                  </article>
                ))}
              </div>
            )}
          </div>
        </section>
      ) : null}

      {show(blocks, 'announcements') && news.length ? (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="webina-shell">
            <h2 className="reveal">{t('site.home.announcements')}</h2>
            <ul className="card-grid cols-2">
              {news.slice(0, 2).map((item) => (
                <li key={item.title} className="panel reveal">
                  <h3>{item.title}</h3>
                  {item.body ? <p className="muted mt-2 text-sm">{item.body}</p> : null}
                </li>
              ))}
            </ul>
          </div>
        </section>
      ) : null}

      {show(blocks, 'blog') ? (
        <section className="section" style={{ paddingTop: 0 }}>
          <div className="webina-shell">
            <div className="section-head">
              <h2 className="reveal">{t('site.home.blog')}</h2>
              <Link href={siteHref(undefined, 'blog')} className="text-sm font-bold text-[var(--saffron-deep)]">
                {t('site.nav.blog')}
              </Link>
            </div>
            {posts.length ? (
              <ul className="card-grid cols-3">
                {posts.slice(0, 3).map((post) => (
                  <li key={post.id} className="reveal">
                    <Link href={siteHref(undefined, `blog/${post.slug}`)} className="panel block">
                      <h3>{post.title}</h3>
                      {post.excerpt ? <p className="muted mt-2 line-clamp-3 text-sm">{post.excerpt}</p> : null}
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="card-grid cols-3">
                {(['blog', 'academy', 'magazine'] as const).map((id) => (
                  <Link key={id} href={siteHref(undefined, id)} className="panel reveal">
                    <h3>{t(`site.nav.${id}`)}</h3>
                    <p className="muted mt-2 text-sm">{t('site.landing.blogEmpty')}</p>
                  </Link>
                ))}
              </div>
            )}
          </div>
        </section>
      ) : null}

      <section className="section" style={{ paddingTop: 0 }}>
        <div className="webina-shell">
          <div className="section-head reveal">
            <h2>{t('site.landing.faqTitle')}</h2>
            <p>{t('site.landing.faqLead')}</p>
          </div>
          <div className="faq mt-6">
            {(faq.length ? faq.slice(0, 6) : fallbackFaq).map((item) => (
              <details key={item.id} className="reveal">
                <summary>{item.question}</summary>
                <dd>{item.answer}</dd>
              </details>
            ))}
          </div>
          <Link href={siteHref(undefined, 'faq')} className="mt-4 inline-block text-sm font-bold text-[var(--saffron-deep)]">
            {t('site.nav.faq')}
          </Link>
        </div>
      </section>

      {show(blocks, 'consultation_cta') ? (
        <section className="webina-shell pb-16">
          <div className="ink-section reveal text-center">
            <h2>{t('site.landing.finalTitle')}</h2>
            <p className="muted mx-auto mt-3 max-w-xl">{t('site.landing.finalLead')}</p>
            <div className="mt-6 flex flex-wrap justify-center gap-3">
              <Link href={siteHref(undefined, 'consultation')} className="btn-saffron" style={{ background: 'var(--saffron)', color: 'var(--ink)' }}>
                {t('site.nav.freeConsultation')}
              </Link>
              <Link href={siteHref(undefined, 'proposal')} className="btn-ghost" style={{ color: 'var(--paper)', borderColor: 'rgba(246,241,232,.2)' }}>
                {t('site.nav.proposal')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}
    </article>
  );
}
