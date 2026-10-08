'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowUpLeft } from 'lucide-react';
import { siteHref } from '@/lib/public-api-server';
import { toPersianDigits } from '@/lib/locale/calendar-date';
import { cn } from '@/lib/utils';
import { PRODUCTS, copyFor } from '../../catalog';
import { INDUSTRY_COUNT, SERVICE_COUNT, SERVICE_MEGA, SOLUTION_MEGA } from '../../site-nav';
import { NavIcon } from '../NavIcon';
import { WorkThumb, isDarkThumb, realCover } from '../WorkThumb';
import { useLandingMotion } from './useLandingMotion';

export type LandingPortfolioItem = {
  id: number;
  slug: string;
  title: string;
  description?: string | null;
  client?: string | null;
  result_metric?: string | null;
  cover_url?: string | null;
  technologies?: string[] | null;
  featured?: boolean | null;
};

export type LandingData = {
  site?: { name?: string; branding?: Record<string, unknown> | null };
  blocks?: { type: string; enabled?: boolean }[];
  testimonials?: { id: number; author: string; quote: string; company?: string | null; role?: string | null }[];
  portfolio?: LandingPortfolioItem[];
  blog?: { id: number; slug: string; title: string; excerpt?: string | null }[];
  announcements?: { id: number; title: string; body?: string | null }[];
  faq?: { id: number; question: string; answer: string }[];
} | null;

const PRODUCT_IDS = new Set(['webino-dashboard', 'webino-erp', 'webino-docs', 'webino-hosting']);

/** The three primary pillars shown on the home page; strategy and support stay in the menu. */
const PILLAR_HREFS = ['services/tech', 'services/growth', 'services/branding'];

function show(blocks: { type: string; enabled?: boolean }[] | undefined, type: string) {
  if (!blocks?.length) return true;
  const row = blocks.find((b) => b.type === type);
  if (!row) return true;
  return row.enabled !== false;
}

function Arrow({ className = 'size-4' }: { className?: string }) {
  return <ArrowUpLeft className={cn(className, 'ltr:-scale-x-100')} aria-hidden />;
}

export function LandingPage({ data }: { data: LandingData }) {
  const t = useTranslations();
  const locale = useLocale();
  const root = useRef<HTMLElement>(null);
  const [dock, setDock] = useState(false);
  useLandingMotion(root, locale);

  useEffect(() => {
    const onScroll = () => {
      const nearEnd = window.scrollY + window.innerHeight > document.documentElement.scrollHeight - 900;
      setDock(window.scrollY > 560 && !nearEnd);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const fa = locale === 'fa';
  const num = (n: number, pad = 2) => {
    const s = String(n).padStart(pad, '0');
    return fa ? toPersianDigits(s) : s;
  };

  const name = data?.site?.name ?? t('site.home.defaultName');
  const blocks = data?.blocks;
  const portfolio = (data?.portfolio ?? [])
    .filter((item) => !PRODUCT_IDS.has(item.slug))
    .sort((a, b) => Number(Boolean(b.featured)) - Number(Boolean(a.featured)))
    .slice(0, 6);
  const quotes = data?.testimonials ?? [];
  const posts = data?.blog ?? [];
  const faq = data?.faq ?? [];
  const news = data?.announcements ?? [];
  const pillars = PILLAR_HREFS.map((href) => SERVICE_MEGA.columns.find((col) => col.href === href)).filter(
    (col): col is (typeof SERVICE_MEGA.columns)[number] => Boolean(col),
  );
  const extraPillars = SERVICE_MEGA.columns.filter((col) => !PILLAR_HREFS.includes(col.href ?? ''));

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

  const heroCount = portfolio.length
    ? { n: portfolio.length, label: t('site.landing.featuredWork'), href: '#work' }
    : { n: SERVICE_COUNT, label: t('site.landing.stat.services'), href: siteHref(undefined, 'services') };

  return (
    <article ref={root} className="webina-landing">
      {show(blocks, 'hero') ? (
        <section className="webina-shell hero">
          <div>
            <p className="webina-kicker hero-kicker">{t('site.landing.kicker')}</p>
            <h1 className="hero-title">
              <span className="line">{t('site.landing.heroLine1')}</span>
              <span className="line">
                {t('site.landing.heroLine2')}
                <span className="dot">.</span>
              </span>
            </h1>
            <p className="hero-lead">{t('site.landing.heroLead', { name })}</p>
            <div className="hero-actions hero-cta">
              <Link href={siteHref(undefined, 'consultation')} className="btn-primary">
                {t('site.nav.freeConsultation')}
              </Link>
              <Link href={siteHref(undefined, 'proposal')} className="btn-ghost">
                {t('site.nav.proposal')}
              </Link>
              <Link href={siteHref(undefined, 'services')} className="text-link">
                {t('site.home.allServices')}
                <Arrow />
              </Link>
            </div>
          </div>
          <a href={heroCount.href} className="hero-count">
            <strong>{num(heroCount.n)}</strong>
            <span>{heroCount.label}</span>
          </a>
        </section>
      ) : null}

      <div className="ribbon" aria-hidden>
        <div className="ribbon-track">
          {[0, 1].map((copy) => (
            <span key={copy} className="flex gap-8">
              {SERVICE_MEGA.columns
                .flatMap((col) => col.items)
                .map((item) => (
                  <span key={`${copy}-${item.href}`}>
                    {t(item.labelKey)}
                    <i>/</i>
                  </span>
                ))}
            </span>
          ))}
        </div>
      </div>

      {show(blocks, 'portfolio_teaser') ? (
        <section id="work" className="section scroll-mt-24">
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <span className="section-index">{num(1)} — {t('site.home.portfolio')}</span>
                <h2>{t('site.landing.workTitle')}</h2>
                <p className="mt-3">{t('site.landing.workLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'portfolio')} className="text-link reveal">
                {t('site.landing.allWork')}
                <Arrow />
              </Link>
            </div>
            {portfolio.length ? (
              <div className="work-grid">
                {portfolio.map((item, i) => {
                  const cover = realCover(item.cover_url);
                  return (
                    <Link key={item.id} href={siteHref(undefined, `portfolio/${item.slug}`)} className="work-card reveal">
                      <div className={cn('work-thumb', !cover && isDarkThumb(i) && 'is-dark')}>
                        {cover ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={cover} alt="" className="absolute inset-0 size-full object-cover" loading="lazy" />
                        ) : (
                          <WorkThumb index={i} />
                        )}
                        <span className="work-no">{num(i + 1)}</span>
                      </div>
                      <div className="work-meta">
                        <h3>{item.title}</h3>
                        {item.result_metric ? <span className="work-metric">{item.result_metric}</span> : null}
                      </div>
                      {item.description ? <p className="work-desc line-clamp-2">{item.description}</p> : null}
                      {item.technologies?.length ? (
                        <div className="work-tags">
                          {item.technologies.slice(0, 3).map((tech) => (
                            <span key={tech}>{tech}</span>
                          ))}
                        </div>
                      ) : null}
                    </Link>
                  );
                })}
              </div>
            ) : (
              <p className="empty-note reveal mt-8">{t('site.landing.portfolioEmpty')}</p>
            )}
          </div>
        </section>
      ) : null}

      {show(blocks, 'services') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <span className="section-index">{num(2)} — {t('site.nav.services')}</span>
                <h2>{t('site.landing.servicesTitle')}</h2>
                <p className="mt-3">{t('site.landing.servicesLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'services')} className="text-link reveal">
                {t('site.home.allServices')}
                <Arrow />
              </Link>
            </div>
            <div className="pillars">
              {pillars.map((col, i) => (
                <article key={col.titleKey} className="pillar reveal">
                  <span className="pillar-no">{num(i + 1)}</span>
                  <h3>
                    <Link href={siteHref(undefined, col.href || 'services')}>{t(col.titleKey)}</Link>
                  </h3>
                  <p>{t(col.leadKey)}</p>
                  <ul>
                    {col.items.slice(0, 5).map((item) => (
                      <li key={item.href}>
                        <Link href={siteHref(undefined, item.href)}>{t(item.labelKey)}</Link>
                      </li>
                    ))}
                  </ul>
                  <Link href={siteHref(undefined, col.href || 'services')} className="text-link">
                    {t('site.landing.viewPillar')}
                    <Arrow />
                  </Link>
                </article>
              ))}
            </div>
            {extraPillars.length ? (
              <p className="pillars-note reveal">
                <span>{t('site.landing.pillarsMore')}</span>
                {extraPillars.map((col) => (
                  <Link key={col.titleKey} href={siteHref(undefined, col.href || 'services')} className="text-link">
                    {t(col.titleKey)}
                    <Arrow className="size-3.5" />
                  </Link>
                ))}
              </p>
            ) : null}
          </div>
        </section>
      ) : null}

      {show(blocks, 'stats') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head reveal">
              <div>
                <span className="section-index">{num(3)} — {t('site.landing.statsKicker')}</span>
                <h2>{t('site.landing.statsTitle')}</h2>
              </div>
            </div>
            <div className="stat-band">
              {stats.map((s) => (
                <div key={s.key} className="reveal">
                  <strong>
                    <span data-count={s.n}>{num(s.n, 1)}</span>
                  </strong>
                  <p>{t(`site.landing.stat.${s.key}`)}</p>
                </div>
              ))}
            </div>
            <p className="muted mt-6 text-xs">{t('site.landing.statNote')}</p>
          </div>
        </section>
      ) : null}

      {show(blocks, 'solutions') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <span className="section-index">{num(4)} — {t('site.nav.solutions')}</span>
                <h2>{t('site.landing.solutionsTitle')}</h2>
                <p className="mt-3">{t('site.landing.solutionsLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'solutions')} className="text-link reveal">
                {t('site.nav.solutions')}
                <Arrow />
              </Link>
            </div>
            <div className="industry-grid">
              {SOLUTION_MEGA.columns.map((col) => (
                <article key={col.titleKey} className="industry reveal">
                  <h3>
                    <span className="mega-ico">
                      <NavIcon name={col.icon} className="size-4" />
                    </span>
                    <Link href={siteHref(undefined, col.href || 'solutions')}>{t(col.titleKey)}</Link>
                  </h3>
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

      {show(blocks, 'process') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head reveal">
              <div>
                <span className="section-index">{num(5)} — {t('site.landing.processKicker')}</span>
                <h2>{t('site.landing.processTitle')}</h2>
                <p className="mt-3">{t('site.landing.processLead')}</p>
              </div>
            </div>
            <ol className="process">
              {(['m1', 'm2', 'm3', 'ongoing'] as const).map((id, i) => (
                <li key={id} className="reveal">
                  <span className="num">{num(i + 1)}</span>
                  <h3 className="mt-3">{t(`site.landing.process.${id}Title`)}</h3>
                  <p className="muted mt-2 text-sm">{t(`site.landing.process.${id}Body`)}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>
      ) : null}

      {show(blocks, 'products') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <span className="section-index">{num(6)} — {t('site.nav.products')}</span>
                <h2>{t('site.landing.productsTitle')}</h2>
                <p className="mt-3">{t('site.landing.productsLead')}</p>
              </div>
              <Link href={siteHref(undefined, 'products')} className="text-link reveal">
                {t('site.nav.products')}
                <Arrow />
              </Link>
            </div>
            <div className="row-list">
              {PRODUCTS.map((product, i) => (
                <Link key={product.id} href={product.href} className="row-item reveal">
                  <span className="row-no">{num(i + 1)}</span>
                  <div>
                    <h3>{copyFor(locale, product.title)}</h3>
                    <p>{copyFor(locale, product.body)}</p>
                  </div>
                  <Arrow className="row-arrow size-5" />
                </Link>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'testimonials') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head reveal">
              <div>
                <h2>{quotes.length ? t('site.home.testimonials') : t('site.landing.principlesTitle')}</h2>
                <p className="mt-3">{quotes.length ? t('site.landing.quotesLead') : t('site.landing.principlesLead')}</p>
              </div>
            </div>
            {quotes.length ? (
              <div className="quote-grid">
                {quotes.slice(0, 3).map((q) => (
                  <blockquote key={q.id} className="quote reveal">
                    {q.quote}
                    <footer>
                      {q.author}
                      {q.company || q.role ? <span>{[q.role, q.company].filter(Boolean).join('، ')}</span> : null}
                    </footer>
                  </blockquote>
                ))}
              </div>
            ) : (
              <ol className="process">
                {(['p1', 'p2', 'p3'] as const).map((id, i) => (
                  <li key={id} className="reveal">
                    <span className="num">{num(i + 1)}</span>
                    <h3 className="mt-3">{t(`site.landing.principle.${id}Title`)}</h3>
                    <p className="muted mt-2 text-sm">{t(`site.landing.principle.${id}Body`)}</p>
                  </li>
                ))}
              </ol>
            )}
          </div>
        </section>
      ) : null}

      {show(blocks, 'announcements') && news.length ? (
        <section className="section">
          <div className="webina-shell">
            <h2 className="reveal">{t('site.home.announcements')}</h2>
            <ul className="card-grid cols-2">
              {news.slice(0, 2).map((item) => (
                <li key={item.id} className="panel reveal">
                  <h3>{item.title}</h3>
                  {item.body ? <p className="muted mt-2 text-sm">{item.body}</p> : null}
                </li>
              ))}
            </ul>
          </div>
        </section>
      ) : null}

      {show(blocks, 'blog') ? (
        <section className="section">
          <div className="webina-shell">
            <div className="section-head">
              <div className="reveal">
                <h2>{t('site.home.blog')}</h2>
              </div>
              <Link href={siteHref(undefined, 'blog')} className="text-link reveal">
                {t('site.nav.blog')}
                <Arrow />
              </Link>
            </div>
            <div className="row-list">
              {posts.length
                ? posts.slice(0, 3).map((post, i) => (
                    <Link key={post.id} href={siteHref(undefined, `blog/${post.slug}`)} className="row-item reveal">
                      <span className="row-no">{num(i + 1)}</span>
                      <div>
                        <h3>{post.title}</h3>
                        {post.excerpt ? <p className="line-clamp-2">{post.excerpt}</p> : null}
                      </div>
                      <Arrow className="row-arrow size-5" />
                    </Link>
                  ))
                : (['blog', 'academy', 'magazine'] as const).map((id, i) => (
                    <Link key={id} href={siteHref(undefined, id)} className="row-item reveal">
                      <span className="row-no">{num(i + 1)}</span>
                      <div>
                        <h3>{t(`site.nav.${id}`)}</h3>
                        <p>{t('site.landing.blogEmpty')}</p>
                      </div>
                      <Arrow className="row-arrow size-5" />
                    </Link>
                  ))}
            </div>
          </div>
        </section>
      ) : null}

      <section className="section">
        <div className="webina-shell grid gap-8 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
          <div className="reveal">
            <h2>{t('site.landing.faqTitle')}</h2>
            <p className="muted mt-3">{t('site.landing.faqLead')}</p>
            <Link href={siteHref(undefined, 'faq')} className="text-link mt-5">
              {t('site.nav.faq')}
              <Arrow />
            </Link>
          </div>
          <div className="faq">
            {(faq.length ? faq.slice(0, 6) : fallbackFaq).map((item) => (
              <details key={item.id} className="reveal">
                <summary>{item.question}</summary>
                <dd>{item.answer}</dd>
              </details>
            ))}
          </div>
        </div>
      </section>

      {show(blocks, 'consultation_cta') ? (
        <section className="cta-bar">
          <div className="webina-shell cta-bar-inner">
            <div>
              <h2>{t('site.landing.ctaBarTitle')}</h2>
              <p>{t('site.landing.ctaBarLead')}</p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link href={siteHref(undefined, 'proposal')} className="btn-primary">
                {t('site.landing.ctaBarButton')}
                <Arrow />
              </Link>
              <Link href={siteHref(undefined, 'consultation')} className="btn-ghost">
                {t('site.nav.freeConsultation')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}

      <div className={cn('mobile-dock', dock && 'is-visible')} aria-hidden={!dock}>
        <span>{t('site.landing.dockLabel')}</span>
        <Link href={siteHref(undefined, 'consultation')} tabIndex={dock ? 0 : -1}>
          {t('site.nav.freeConsultation')}
        </Link>
      </div>
    </article>
  );
}
