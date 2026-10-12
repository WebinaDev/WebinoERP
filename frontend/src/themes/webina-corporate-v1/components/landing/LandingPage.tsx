'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowUpLeft, Check } from 'lucide-react';
import { siteHref } from '@/lib/public-api-server';
import { formatCalendarDate, toPersianDigits } from '@/lib/locale/calendar-date';
import { cn } from '@/lib/utils';
import { INDUSTRY_COUNT, SERVICE_COUNT } from '../../site-nav';
import {
  HOME_ABOUT_POINTS,
  HOME_COURSES,
  HOME_MODULES,
  HOME_SERVICES,
  HOME_STEPS,
  HOME_WHY,
} from '../../home-content';
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
  industry?: { name?: string | null } | null;
};

export type LandingData = {
  site?: { name?: string; branding?: Record<string, unknown> | null };
  blocks?: { type: string; enabled?: boolean }[];
  testimonials?: { id: number; author: string; quote: string; company?: string | null; role?: string | null; avatar_url?: string | null }[];
  portfolio?: LandingPortfolioItem[];
  blog?: { id: number; slug: string; title: string; excerpt?: string | null; cover_url?: string | null; published_at?: string | null }[];
  courses?: { id: number; slug: string; title: string; description?: string | null; cover_url?: string | null }[];
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

function Arrow({ className = 'size-4' }: { className?: string }) {
  return <ArrowUpLeft className={cn(className, 'ltr:-scale-x-100')} aria-hidden />;
}

function SectionHead({
  index,
  title,
  subtitle,
  action,
}: {
  index: string;
  title: string;
  subtitle?: string;
  action?: { href: string; label: string };
}) {
  return (
    <div className="section-head">
      <div className="reveal">
        <span className="section-index">{index}</span>
        <h2>{title}</h2>
        {subtitle ? <p className="mt-3">{subtitle}</p> : null}
      </div>
      {action ? (
        <Link href={action.href} className="text-link reveal">
          {action.label}
          <Arrow />
        </Link>
      ) : null}
    </div>
  );
}

/** Decorative dashboard: shapes only, no numbers, so nothing reads as an invented statistic. */
function DashboardArt() {
  return (
    <svg viewBox="0 0 520 360" className="dash-art" aria-hidden xmlns="http://www.w3.org/2000/svg">
      <rect x="0.5" y="0.5" width="519" height="359" rx="22" fill="#fff" stroke="rgba(34,36,42,.12)" />
      <rect x="0" y="0" width="520" height="44" rx="22" fill="#f2f6ff" />
      <rect x="0" y="22" width="520" height="22" fill="#f2f6ff" />
      <circle cx="24" cy="22" r="5" fill="#0054ff" />
      <circle cx="42" cy="22" r="5" fill="#c9d8ff" />
      <circle cx="60" cy="22" r="5" fill="#c9d8ff" />
      <rect x="380" y="16" width="116" height="12" rx="6" fill="#dfe8ff" />
      <rect x="24" y="64" width="140" height="70" rx="14" fill="#0054ff" />
      <rect x="40" y="82" width="60" height="8" rx="4" fill="#fff" opacity=".7" />
      <rect x="40" y="100" width="92" height="16" rx="6" fill="#fff" />
      <rect x="180" y="64" width="140" height="70" rx="14" fill="#f2f6ff" />
      <rect x="196" y="82" width="60" height="8" rx="4" fill="#9db6ff" />
      <rect x="196" y="100" width="80" height="16" rx="6" fill="#22242a" />
      <rect x="336" y="64" width="160" height="70" rx="14" fill="#22242a" />
      <rect x="352" y="82" width="60" height="8" rx="4" fill="#fff" opacity=".5" />
      <rect x="352" y="100" width="100" height="16" rx="6" fill="#fff" />
      <rect x="24" y="152" width="300" height="184" rx="14" fill="#fff" stroke="rgba(34,36,42,.08)" />
      <path d="M44 300 C 90 290 110 250 150 252 S 210 220 240 200 S 290 170 304 172" fill="none" stroke="#0054ff" strokeWidth="4" strokeLinecap="round" />
      <path d="M44 300 C 90 290 110 250 150 252 S 210 220 240 200 S 290 170 304 172 V 316 H 44 Z" fill="#0054ff" opacity=".08" />
      {[0, 1, 2, 3].map((i) => (
        <line key={i} x1="44" x2="304" y1={192 + i * 36} y2={192 + i * 36} stroke="rgba(34,36,42,.06)" />
      ))}
      <rect x="336" y="152" width="160" height="184" rx="14" fill="#f2f6ff" />
      {[56, 92, 70, 120, 104, 140].map((h, i) => (
        <rect key={i} x={352 + i * 24} y={316 - h} width="14" height={h} rx="5" fill={i === 5 ? '#0054ff' : '#bccdff'} />
      ))}
    </svg>
  );
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
  const sec = (n: number) => num(n);

  const blocks = data?.blocks;
  const portfolio = (data?.portfolio ?? [])
    .filter((item) => !PRODUCT_IDS.has(item.slug))
    .sort((a, b) => Number(Boolean(b.featured)) - Number(Boolean(a.featured)));
  const featured = portfolio.slice(0, 6);
  const highlight = portfolio.find((item) => item.result_metric);
  const quotes = data?.testimonials ?? [];
  const posts = (data?.blog ?? []).slice(0, 4);
  const apiCourses = data?.courses ?? [];
  const courses = apiCourses.length
    ? apiCourses.slice(0, 5).map((course) => ({ slug: course.slug, title: course.title }))
    : HOME_COURSES.map((course) => ({ slug: course.slug, title: t(`site.v2.course.${course.key}`) }));

  const heroStats = [
    portfolio.length ? { value: num(portfolio.length, 1), label: t('site.v2.heroStatProjects') } : null,
    { value: num(SERVICE_COUNT, 1), label: t('site.v2.heroStatServices') },
    { value: num(INDUSTRY_COUNT, 1), label: t('site.v2.heroStatIndustries') },
  ].filter(Boolean) as { value: string; label: string }[];

  return (
    <article ref={root} className="webina-landing v2">
      {show(blocks, 'hero') ? (
        <section className="webina-shell hero2">
          <div className="hero2-copy">
            <h1 className="hero2-title hero-title">
              <span className="line">{t('site.v2.heroTitle')}</span>
            </h1>
            <p className="hero2-subtitle hero-lead">
              {t('site.v2.heroSubtitle1')}
              <span>{t('site.v2.heroSubtitle2')}</span>
            </p>
            <p className="hero2-text hero-lead">
              {t('site.v2.heroText1')}
              <br />
              {t('site.v2.heroText2')}
            </p>
            <div className="hero-actions hero-cta">
              <Link href={siteHref(undefined, 'consultation')} className="btn-primary">
                {t('site.v2.heroCta1')}
              </Link>
              <a href="#process" className="btn-ghost">
                {t('site.v2.heroCta2')}
              </a>
              <Link href={siteHref(undefined, 'proposal')} className="text-link">
                {t('site.v2.heroCta3')}
                <Arrow />
              </Link>
            </div>
          </div>
          <div className="hero2-visual hero-count" aria-label={t('site.v2.heroStatProjects')}>
            <DashboardArt />
            <div className="float-stat fs-1">
              <strong>{heroStats[0]?.value}</strong>
              <span>{heroStats[0]?.label}</span>
            </div>
            {heroStats[1] ? (
              <div className="float-stat fs-2">
                <strong>{heroStats[1].value}</strong>
                <span>{heroStats[1].label}</span>
              </div>
            ) : null}
            {highlight ? (
              <Link href={siteHref(undefined, `portfolio/${highlight.slug}`)} className="float-stat fs-3 is-blue">
                <small>{t('site.v2.heroStatResult')}</small>
                <strong>{highlight.result_metric}</strong>
                <span>{highlight.title}</span>
              </Link>
            ) : heroStats[2] ? (
              <div className="float-stat fs-3">
                <strong>{heroStats[2].value}</strong>
                <span>{heroStats[2].label}</span>
              </div>
            ) : null}
          </div>
        </section>
      ) : null}

      {show(blocks, 'about') ? (
        <section className="section">
          <div className="webina-shell about2">
            <div className="reveal">
              <span className="section-index">{sec(1)}</span>
              <h2>{t('site.v2.aboutTitle')}</h2>
            </div>
            <div className="reveal">
              <p className="about2-lead">{t('site.v2.aboutBody')}</p>
              <p className="muted mt-6 font-bold">{t('site.v2.aboutListLead')}</p>
              <ul className="check-list">
                {HOME_ABOUT_POINTS.map((key) => (
                  <li key={key}>
                    <Check className="size-4" aria-hidden />
                    {t(`site.v2.about.${key}`)}
                  </li>
                ))}
              </ul>
              <p className="about2-closing">{t('site.v2.aboutClosing')}</p>
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'services') ? (
        <section className="section">
          <div className="webina-shell">
            <SectionHead index={sec(2)} title={t('site.v2.servicesTitle')} subtitle={t('site.v2.servicesSubtitle')} />
            <div className="svc-grid">
              {HOME_SERVICES.map((service, i) => (
                <Link key={service.slug} href={siteHref(undefined, `services/${service.slug}`)} className={cn('svc-card reveal', i === 6 && 'is-vip')}>
                  <span className="svc-top">
                    <span className="mega-ico">
                      <NavIcon name={service.icon} className="size-4" />
                    </span>
                    <span className="svc-no">{num(i + 1)}</span>
                  </span>
                  <h3>{t(`site.v2.service.${service.key}Title`)}</h3>
                  <p>{t(`site.v2.service.${service.key}Body`)}</p>
                  <Arrow className="svc-arrow size-5" />
                </Link>
              ))}
            </div>
            <div className="mt-8">
              <Link href={siteHref(undefined, 'services')} className="btn-ghost">
                {t('site.v2.servicesCta')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'products') ? (
        <section className="section">
          <div className="webina-shell">
            <SectionHead index={sec(3)} title={t('site.v2.productsTitle')} subtitle={t('site.v2.productsSubtitle')} />
            <p className="muted mt-4 max-w-2xl reveal">{t('site.v2.productsIntro')}</p>
            <div className="mod-grid">
              {HOME_MODULES.map((mod, i) => (
                <Link key={mod.id} href={siteHref(undefined, `products#${mod.id}`)} className="mod-card reveal">
                  <span className="mega-ico">
                    <NavIcon name={mod.icon} className="size-4" />
                  </span>
                  <span className="mod-no">{num(i + 1)}</span>
                  <h3>{t(`site.v2.module.${mod.key}`)}</h3>
                </Link>
              ))}
            </div>
            <div className="mt-8">
              <Link href={siteHref(undefined, 'products')} className="btn-ghost">
                {t('site.v2.productsCta')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'portfolio_teaser') ? (
        <section id="work" className="section scroll-mt-24">
          <div className="webina-shell">
            <SectionHead
              index={sec(4)}
              title={t('site.v2.workTitle')}
              subtitle={t('site.v2.workSubtitle')}
              action={{ href: siteHref(undefined, 'portfolio'), label: t('site.v2.workCta') }}
            />
            {featured.length ? (
              <div className="work-grid">
                {featured.map((item, i) => {
                  const cover = realCover(item.cover_url);
                  return (
                    <article key={item.id} className="work-card reveal">
                      <Link href={siteHref(undefined, `portfolio/${item.slug}`)} className="block">
                        <div className={cn('work-thumb', !cover && isDarkThumb(i) && 'is-dark')}>
                          {cover ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img src={cover} alt={item.title} className="absolute inset-0 size-full object-cover" loading="lazy" />
                          ) : (
                            <WorkThumb index={i} />
                          )}
                          <span className="work-no">{num(i + 1)}</span>
                        </div>
                      </Link>
                      <div className="work-meta">
                        <h3>{item.client || item.title}</h3>
                        {item.industry?.name ? <span className="work-industry">{item.industry.name}</span> : null}
                      </div>
                      {item.result_metric ? <p className="work-metric mt-2">{item.result_metric}</p> : null}
                      {item.description ? <p className="work-desc line-clamp-2">{item.description}</p> : null}
                      <Link href={siteHref(undefined, `portfolio/${item.slug}`)} className="text-link mt-3">
                        {t('site.v2.workDetails')}
                        <Arrow />
                      </Link>
                    </article>
                  );
                })}
              </div>
            ) : (
              <p className="empty-note reveal mt-8">{t('site.landing.portfolioEmpty')}</p>
            )}
          </div>
        </section>
      ) : null}

      {show(blocks, 'process') ? (
        <section id="process" className="section scroll-mt-24">
          <div className="webina-shell">
            <SectionHead index={sec(5)} title={t('site.v2.processTitle')} />
            <ol className="steps">
              {HOME_STEPS.map((id, i) => (
                <li key={id} className={cn('reveal', i === 5 && 'is-vip')}>
                  <span className="step-no">{num(i + 1)}</span>
                  <h3>{t(`site.v2.step.${id}`)}</h3>
                </li>
              ))}
            </ol>
            <div className="mt-8">
              <Link href={siteHref(undefined, 'consultation')} className="btn-primary">
                {t('site.v2.processCta')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'academy') ? (
        <section className="section">
          <div className="webina-shell">
            <SectionHead
              index={sec(6)}
              title={t('site.v2.academyTitle')}
              subtitle={t('site.v2.academySubtitle')}
              action={{ href: siteHref(undefined, 'academy'), label: t('site.v2.academyCta') }}
            />
            <p className="muted mt-4 max-w-2xl reveal">{t('site.v2.academyText')}</p>
            <div className="row-list">
              {courses.map((course, i) => (
                <Link key={course.slug} href={siteHref(undefined, `academy/${course.slug}`)} className="row-item reveal">
                  <span className="row-no">{num(i + 1)}</span>
                  <div>
                    <h3>{course.title}</h3>
                  </div>
                  <Arrow className="row-arrow size-5" />
                </Link>
              ))}
            </div>
            <div className="mt-8">
              <Link href={siteHref(undefined, 'academy')} className="btn-ghost">
                {t('site.v2.academyCta')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'blog') ? (
        <section className="section">
          <div className="webina-shell">
            <SectionHead
              index={sec(7)}
              title={t('site.v2.magazineTitle')}
              subtitle={t('site.v2.magazineSubtitle')}
              action={{ href: siteHref(undefined, 'blog'), label: t('site.v2.magazineCta') }}
            />
            {posts.length ? (
              <div className="post-grid">
                {posts.map((post, i) => (
                  <article key={post.id} className="post-card reveal">
                    <Link href={siteHref(undefined, `blog/${post.slug}`)} className="block">
                      <div className={cn('work-thumb post-thumb', !post.cover_url && isDarkThumb(i + 3) && 'is-dark')}>
                        {post.cover_url ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={post.cover_url} alt={post.title} className="absolute inset-0 size-full object-cover" loading="lazy" />
                        ) : (
                          <WorkThumb index={i + 3} />
                        )}
                      </div>
                    </Link>
                    {post.published_at ? (
                      <time className="post-date" dateTime={post.published_at}>
                        {formatCalendarDate(post.published_at, fa ? 'fa' : 'en')}
                      </time>
                    ) : null}
                    <h3>
                      <Link href={siteHref(undefined, `blog/${post.slug}`)}>{post.title}</Link>
                    </h3>
                    {post.excerpt ? <p className="work-desc line-clamp-2">{post.excerpt}</p> : null}
                    <Link href={siteHref(undefined, `blog/${post.slug}`)} className="text-link mt-3">
                      {t('site.v2.readMore')}
                      <Arrow />
                    </Link>
                  </article>
                ))}
              </div>
            ) : (
              <p className="empty-note reveal mt-8">{t('site.v2.magazineEmpty')}</p>
            )}
            <div className="mt-8">
              <Link href={siteHref(undefined, 'blog')} className="btn-ghost">
                {t('site.v2.magazineCta')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}

      {show(blocks, 'why') ? (
        <section className="section why2">
          <div className="webina-shell">
            <SectionHead index={sec(8)} title={t('site.v2.whyTitle')} />
            <ul className="why-grid">
              {HOME_WHY.map((key, i) => (
                <li key={key} className="reveal">
                  <span className="why-no">{num(i + 1)}</span>
                  <p>{t(`site.v2.why.${key}`)}</p>
                </li>
              ))}
            </ul>
          </div>
        </section>
      ) : null}

      {show(blocks, 'testimonials') ? (
        <section className="section">
          <div className="webina-shell">
            <SectionHead index={sec(9)} title={t('site.v2.testimonialsTitle')} />
            {quotes.length ? (
              <div className="quote-grid">
                {quotes.slice(0, 6).map((q) => (
                  <blockquote key={q.id} className="quote reveal">
                    {q.quote}
                    <footer className="flex items-center gap-3">
                      {q.avatar_url ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={q.avatar_url} alt="" className="size-10 rounded-full object-cover" />
                      ) : null}
                      <span className="!mt-0 !font-extrabold !text-[var(--ink)]">
                        {q.author}
                        {q.role || q.company ? (
                          <span className="block text-xs font-normal text-[var(--muted)]">
                            {[q.role, q.company].filter(Boolean).join('، ')}
                          </span>
                        ) : null}
                      </span>
                    </footer>
                  </blockquote>
                ))}
              </div>
            ) : (
              <p className="empty-note reveal mt-8">{t('site.v2.testimonialsEmpty')}</p>
            )}
          </div>
        </section>
      ) : null}

      {show(blocks, 'consultation_cta') ? (
        <section className="cta-bar">
          <div className="webina-shell cta-bar-inner">
            <div>
              <h2>{t('site.v2.ctaTitle')}</h2>
              <p>
                {t('site.v2.ctaText1')}
                <br />
                {t('site.v2.ctaText2')}
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link href={siteHref(undefined, 'consultation')} className="btn-primary">
                {t('site.v2.ctaBtn1')}
                <Arrow />
              </Link>
              <Link href={siteHref(undefined, 'proposal')} className="btn-ghost">
                {t('site.v2.ctaBtn2')}
              </Link>
              <Link href={siteHref(undefined, 'contact')} className="btn-ghost">
                {t('site.v2.ctaBtn3')}
              </Link>
            </div>
          </div>
        </section>
      ) : null}

      <div className={cn('mobile-dock', dock && 'is-visible')} aria-hidden={!dock}>
        <span>{t('site.landing.dockLabel')}</span>
        <Link href={siteHref(undefined, 'consultation')} tabIndex={dock ? 0 : -1}>
          {t('site.v2.heroCta1')}
        </Link>
      </div>
    </article>
  );
}
