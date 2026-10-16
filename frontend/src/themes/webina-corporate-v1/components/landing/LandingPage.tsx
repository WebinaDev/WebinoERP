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
  isExternalHref,
  resolveHomeBlocks,
  type ButtonItem,
  type HomeProps,
  type RawBlock,
  type StatItem,
} from '../../site-content';
import { NavIcon } from '../NavIconClient';
import { TestimonialSlider } from './TestimonialSlider';
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
  blocks?: RawBlock[];
  testimonials?: { id: number; author: string; quote: string; company?: string | null; role?: string | null; avatar_url?: string | null; rating?: number | null }[];
  portfolio?: LandingPortfolioItem[];
  blog?: { id: number; slug: string; title: string; excerpt?: string | null; cover_url?: string | null; published_at?: string | null }[];
  courses?: { id: number; slug: string; title: string; description?: string | null; cover_url?: string | null }[];
  announcements?: { id: number; title: string; body?: string | null }[];
  faq?: { id: number; question: string; answer: string }[];
} | null;

const PRODUCT_IDS = new Set(['webino-dashboard', 'webino-erp', 'webino-docs', 'webino-hosting']);

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

function href(path: string) {
  return isExternalHref(path) ? path : siteHref(undefined, path.replace(/^\//, ''));
}

function SmartLink({ to, className, children, tabIndex }: { to: string; className?: string; children: React.ReactNode; tabIndex?: number }) {
  if (/^(https?:|tel:|mailto:)/.test(to)) {
    return (
      <a href={to} className={className} tabIndex={tabIndex} target={to.startsWith('http') ? '_blank' : undefined} rel="noreferrer">
        {children}
      </a>
    );
  }
  if (to.startsWith('#')) {
    return (
      <a href={to} className={className} tabIndex={tabIndex}>
        {children}
      </a>
    );
  }
  return (
    <Link href={href(to)} className={className} tabIndex={tabIndex}>
      {children}
    </Link>
  );
}

function Buttons({ items, arrowFirst }: { items: ButtonItem[]; arrowFirst?: boolean }) {
  return (
    <>
      {items
        .filter((b) => b.label && b.href)
        .map((b, i) => (
          <SmartLink key={`${b.href}-${i}`} to={b.href} className={b.style === 'link' ? 'text-link' : b.style === 'ghost' ? 'btn-ghost' : 'btn-primary'}>
            {b.label}
            {b.style === 'link' || (arrowFirst && b.style === 'primary') ? <Arrow /> : null}
          </SmartLink>
        ))}
    </>
  );
}

function Cta({ label, to, className = 'btn-ghost' }: { label?: string; to?: string; className?: string }) {
  if (!label || !to) return null;
  return (
    <div className="mt-8">
      <SmartLink to={to} className={className}>
        {label}
      </SmartLink>
    </div>
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

  const blocks = resolveHomeBlocks(data?.blocks, t, fa);
  const portfolio = (data?.portfolio ?? [])
    .filter((item) => !PRODUCT_IDS.has(item.slug))
    .sort((a, b) => Number(Boolean(b.featured)) - Number(Boolean(a.featured)));
  const highlight = portfolio.find((item) => item.result_metric);
  const quotes = data?.testimonials ?? [];
  const hero = blocks.find((b) => b.type === 'hero')?.props as HomeProps['hero'] | undefined;

  function statValue(stat: StatItem): { value: string; caption?: string; href?: string } | null {
    switch (stat.source) {
      case 'portfolio_count':
        return portfolio.length ? { value: num(portfolio.length, 1) } : null;
      case 'service_count':
        return { value: num(SERVICE_COUNT, 1) };
      case 'industry_count':
        return { value: num(INDUSTRY_COUNT, 1) };
      case 'highlight_result':
        return highlight?.result_metric
          ? { value: highlight.result_metric, caption: highlight.title, href: `portfolio/${highlight.slug}` }
          : null;
      default:
        return stat.value ? { value: fa ? toPersianDigits(stat.value) : stat.value } : null;
    }
  }

  let index = 0;
  const sections = blocks.map((block) => {
    switch (block.type) {
      case 'hero': {
        const p = block.props;
        const stats = p.stats
          .map((s) => ({ stat: s, data: statValue(s) }))
          .filter((s): s is { stat: StatItem; data: NonNullable<ReturnType<typeof statValue>> } => Boolean(s.data))
          .slice(0, 3);
        return (
          <section key="hero" className="webina-shell hero2">
            <div className="hero2-copy">
              <h1 className="hero2-title hero-title">
                <span className="line">{p.title}</span>
              </h1>
              <p className="hero2-subtitle hero-lead">
                {p.subtitle_1}
                {p.subtitle_2 ? <span>{p.subtitle_2}</span> : null}
              </p>
              <p className="hero2-text hero-lead">
                {p.text_1}
                {p.text_2 ? (
                  <>
                    <br />
                    {p.text_2}
                  </>
                ) : null}
              </p>
              <div className="hero-actions hero-cta">
                <Buttons items={p.buttons} />
              </div>
            </div>
            <div className="hero2-visual hero-count">
              <DashboardArt />
              {stats.map(({ stat, data: d }, i) => {
                const cls = cn('float-stat', `fs-${i + 1}`, stat.source === 'highlight_result' && 'is-blue');
                const inner = (
                  <>
                    {d.caption ? <small>{stat.label}</small> : null}
                    <strong>{d.value}</strong>
                    <span>{d.caption ?? stat.label}</span>
                  </>
                );
                return d.href ? (
                  <Link key={i} href={href(d.href)} className={cls}>
                    {inner}
                  </Link>
                ) : (
                  <div key={i} className={cls}>
                    {inner}
                  </div>
                );
              })}
            </div>
          </section>
        );
      }
      case 'about': {
        const p = block.props;
        index += 1;
        return (
          <section key="about" className="section">
            <div className="webina-shell about2">
              <div className="reveal">
                <span className="section-index">{num(index)}</span>
                <h2>{p.title}</h2>
              </div>
              <div className="reveal">
                <p className="about2-lead">{p.body}</p>
                {p.list_lead ? <p className="muted mt-6 font-bold">{p.list_lead}</p> : null}
                <ul className="check-list">
                  {p.points.map((point, i) => (
                    <li key={i}>
                      <Check className="size-4" aria-hidden />
                      {point.text}
                    </li>
                  ))}
                </ul>
                {p.closing ? <p className="about2-closing">{p.closing}</p> : null}
              </div>
            </div>
          </section>
        );
      }
      case 'services': {
        const p = block.props;
        index += 1;
        return (
          <section key="services" id="services" className="section scroll-mt-24">
            <div className="webina-shell">
              <SectionHead index={num(index)} title={p.title} subtitle={p.subtitle} />
              <div className="svc-grid">
                {p.items.map((service, i) => (
                  <SmartLink key={`${service.href}-${i}`} to={service.href || 'services'} className={cn('svc-card reveal', service.vip && 'is-vip')}>
                    <span className="svc-top">
                      <span className="mega-ico">
                        <NavIcon name={service.icon || 'Sparkles'} className="size-4" />
                      </span>
                      <span className="svc-no">{num(i + 1)}</span>
                    </span>
                    <h3>{service.title}</h3>
                    <p>{service.body}</p>
                    <Arrow className="svc-arrow size-5" />
                  </SmartLink>
                ))}
              </div>
              <Cta label={p.cta_label} to={p.cta_href} />
            </div>
          </section>
        );
      }
      case 'products': {
        const p = block.props;
        index += 1;
        return (
          <section key="products" className="section">
            <div className="webina-shell">
              <SectionHead index={num(index)} title={p.title} subtitle={p.subtitle} />
              {p.intro ? <p className="muted mt-4 max-w-2xl reveal">{p.intro}</p> : null}
              <div className="mod-grid">
                {p.items.map((mod, i) => (
                  <SmartLink key={`${mod.anchor}-${i}`} to={`products${mod.anchor ? `#${mod.anchor}` : ''}`} className="mod-card reveal">
                    <span className="mega-ico">
                      <NavIcon name={mod.icon || 'Boxes'} className="size-4" />
                    </span>
                    <span className="mod-no">{num(i + 1)}</span>
                    <h3>{mod.title}</h3>
                  </SmartLink>
                ))}
              </div>
              <Cta label={p.cta_label} to={p.cta_href} />
            </div>
          </section>
        );
      }
      case 'portfolio_teaser': {
        const p = block.props;
        index += 1;
        const featured = portfolio.slice(0, Math.max(1, Number(p.limit) || 6));
        return (
          <section key="work" id="work" className="section scroll-mt-24">
            <div className="webina-shell">
              <SectionHead
                index={num(index)}
                title={p.title}
                subtitle={p.subtitle}
                action={p.cta_label && p.cta_href ? { href: href(p.cta_href), label: p.cta_label } : undefined}
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
                          {p.details_label}
                          <Arrow />
                        </Link>
                      </article>
                    );
                  })}
                </div>
              ) : (
                <p className="empty-note reveal mt-8">{p.empty}</p>
              )}
            </div>
          </section>
        );
      }
      case 'process': {
        const p = block.props;
        index += 1;
        return (
          <section key="process" id={p.anchor || 'process'} className="section scroll-mt-24">
            <div className="webina-shell">
              <SectionHead index={num(index)} title={p.title} />
              <ol className="steps">
                {p.steps.map((step, i) => (
                  <li key={i} className={cn('reveal', step.highlight && 'is-vip')}>
                    <span className="step-no">{num(i + 1)}</span>
                    <h3>{step.text}</h3>
                  </li>
                ))}
              </ol>
              <Cta label={p.cta_label} to={p.cta_href} className="btn-primary" />
            </div>
          </section>
        );
      }
      case 'academy': {
        const p = block.props;
        index += 1;
        const apiCourses = data?.courses ?? [];
        const courses: { slug: string; title: string; cover_url?: string | null }[] = apiCourses.length
          ? apiCourses.slice(0, 5).map((c) => ({ slug: c.slug, title: c.title, cover_url: c.cover_url }))
          : (p.fallback_courses ?? []).map((c) => ({ slug: c.slug, title: c.title, cover_url: (c as { cover_url?: string | null }).cover_url ?? null }));
        return (
          <section key="academy" className="section">
            <div className="webina-shell">
              <SectionHead
                index={num(index)}
                title={p.title}
                subtitle={p.subtitle}
                action={p.cta_label && p.cta_href ? { href: href(p.cta_href), label: p.cta_label } : undefined}
              />
              {p.text ? <p className="muted mt-4 max-w-2xl reveal">{p.text}</p> : null}
              <div className="row-list">
                {courses.map((course, i) => (
                  <Link key={`${course.slug}-${i}`} href={siteHref(undefined, course.slug ? `academy/${course.slug}` : 'academy')} className="row-item reveal">
                    <span className="row-no">{num(i + 1)}</span>
                    <div className="row-main">
                      {course.cover_url ? (
                        <span className="row-thumb" aria-hidden>
                          {/* eslint-disable-next-line @next/next/no-img-element */}
                          <img src={course.cover_url} alt="" loading="lazy" />
                        </span>
                      ) : null}
                      <h3>{course.title}</h3>
                    </div>
                    <Arrow className="row-arrow size-5" />
                  </Link>
                ))}
              </div>
              <Cta label={p.cta_label} to={p.cta_href} />
            </div>
          </section>
        );
      }
      case 'blog': {
        const p = block.props;
        index += 1;
        const posts = (data?.blog ?? []).slice(0, Math.max(1, Number(p.limit) || 4));
        return (
          <section key="blog" className="section">
            <div className="webina-shell">
              <SectionHead
                index={num(index)}
                title={p.title}
                subtitle={p.subtitle}
                action={p.cta_label && p.cta_href ? { href: href(p.cta_href), label: p.cta_label } : undefined}
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
                        {p.read_more}
                        <Arrow />
                      </Link>
                    </article>
                  ))}
                </div>
              ) : (
                <p className="empty-note reveal mt-8">{p.empty}</p>
              )}
              <Cta label={p.cta_label} to={p.cta_href} />
            </div>
          </section>
        );
      }
      case 'why': {
        const p = block.props;
        index += 1;
        return (
          <section key="why" className="section why2">
            <div className="webina-shell">
              <SectionHead index={num(index)} title={p.title} />
              <ul className="why-grid">
                {p.items.map((item, i) => (
                  <li key={i} className="reveal">
                    <span className="why-no">{num(i + 1)}</span>
                    <p>{item.text}</p>
                  </li>
                ))}
              </ul>
            </div>
          </section>
        );
      }
      case 'testimonials': {
        const p = block.props;
        index += 1;
        return (
          <section key="testimonials" className="section">
            <div className="webina-shell">
              <SectionHead index={num(index)} title={p.title} />
              {quotes.length ? (
                <TestimonialSlider
                  items={quotes.slice(0, Math.max(1, Number(p.limit) || 8))}
                  autoplay={p.autoplay !== false}
                  interval={Number(p.interval) || 6}
                  showStars={p.show_stars !== false}
                  showArrows={p.show_arrows !== false}
                />
              ) : (
                <p className="empty-note reveal mt-8">{p.empty}</p>
              )}
            </div>
          </section>
        );
      }
      case 'consultation_cta': {
        const p = block.props;
        return (
          <section key="cta" className="cta-bar">
            <div className="webina-shell cta-bar-inner">
              <div>
                <h2>{p.title}</h2>
                <p>
                  {p.text_1}
                  {p.text_2 ? (
                    <>
                      <br />
                      {p.text_2}
                    </>
                  ) : null}
                </p>
              </div>
              <div className="flex flex-wrap gap-3">
                <Buttons items={p.buttons} arrowFirst />
              </div>
            </div>
          </section>
        );
      }
      default:
        return null;
    }
  });

  return (
    <article ref={root} className="webina-landing v2">
      {sections}
      {hero?.dock_cta_label && hero.dock_cta_href ? (
        <div className={cn('mobile-dock', dock && 'is-visible')} aria-hidden={!dock}>
          <span>{hero.dock_label}</span>
          <SmartLink to={hero.dock_cta_href} tabIndex={dock ? 0 : -1}>
            {hero.dock_cta_label}
          </SmartLink>
        </div>
      ) : null}
    </article>
  );
}
