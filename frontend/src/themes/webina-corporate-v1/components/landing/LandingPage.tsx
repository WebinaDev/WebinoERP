'use client';

import Link from 'next/link';
import { useRef } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowUpRight, Sparkles } from 'lucide-react';
import { siteHref } from '@/lib/public-api-server';
import { SERVICE_MEGA, SOLUTION_MEGA } from '../../site-nav';
import { cn } from '@/lib/utils';
import { useLandingMotion } from './useLandingMotion';
import './landing.css';

type Cms = {
  site?: { name?: string; branding?: Record<string, unknown> | null };
  testimonials?: { id: number; author: string; quote: string; company?: string | null }[];
  portfolio?: { id: number; slug: string; title: string; description?: string | null }[];
  blog?: { id: number; slug: string; title: string; excerpt?: string | null }[];
} | null;

const STATS = [
  { key: 'projects', n: 400 },
  { key: 'months', n: 6 },
  { key: 'intent', n: 100 },
  { key: 'retainer', n: 30 },
] as const;

const VALUES = ['cmo', 'google', 'funnel', 'guarantee', 'roi'] as const;
const PROCESS = ['m1', 'm2', 'm3', 'ongoing'] as const;
const CHANNELS = ['direct', 'network', 'linkedin', 'seo'] as const;
const SEGMENTS = ['industrial', 'medical', 'luxury', 'construction'] as const;

const FALLBACK_QUOTES = [
  { authorKey: 'q1a', roleKey: 'q1r', quoteKey: 'q1' },
  { authorKey: 'q2a', roleKey: 'q2r', quoteKey: 'q2' },
  { authorKey: 'q3a', roleKey: 'q3r', quoteKey: 'q3' },
] as const;

export function LandingPage({ data }: { data: Cms }) {
  const t = useTranslations();
  const locale = useLocale();
  const root = useRef<HTMLElement>(null);
  useLandingMotion(root, locale);
  const name = data?.site?.name ?? t('site.home.defaultName');
  const rtl = locale === 'fa';

  const onMagnetic = (e: React.MouseEvent<HTMLAnchorElement>) => {
    const el = e.currentTarget;
    const r = el.getBoundingClientRect();
    const x = e.clientX - r.left - r.width / 2;
    const y = e.clientY - r.top - r.height / 2;
    el.style.transform = `translate(${x * 0.18}px, ${y * 0.18}px)`;
  };
  const resetMagnetic = (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.currentTarget.style.transform = '';
  };

  return (
    <article ref={root} className="webina-landing relative overflow-hidden" dir={rtl ? 'rtl' : 'ltr'}>
      <section className="relative isolate min-h-[88vh] overflow-hidden">
        <div className="orb orb-a -start-24 top-10 size-[28rem] bg-[#0066FF]/35" />
        <div className="orb orb-b -end-16 bottom-0 size-[22rem] bg-[#4d94ff]/20" />
        <div className="grain" />
        <div className="relative mx-auto flex min-h-[88vh] max-w-7xl flex-col justify-center px-4 py-20 lg:px-6">
          <p className="hero-kicker mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs tracking-wide text-[#9cc4ff]">
            <Sparkles className="size-3.5" />
            {t('site.landing.kicker')}
          </p>
          <h1 className="hero-title max-w-5xl text-4xl font-black leading-[1.15] tracking-tight text-white sm:text-5xl lg:text-7xl">
            {t('site.landing.heroTitle')
              .split(' ')
              .map((word, i) => (
                <span key={`${word}-${i}`} className="me-2 inline-block">
                  {word}
                </span>
              ))}
          </h1>
          <p className="hero-lead mt-6 max-w-2xl text-base text-white/65 sm:text-lg">{t('site.landing.heroLead', { name })}</p>
          <div className="hero-cta mt-10 flex flex-wrap items-center gap-3">
            <Link
              href={siteHref(undefined, 'consultation')}
              onMouseMove={onMagnetic}
              onMouseLeave={resetMagnetic}
              className="magnetic-btn rounded-full bg-[#0066FF] px-7 py-3.5 text-sm font-semibold text-white shadow-[0_0_40px_#0066FF66] transition-transform"
            >
              {t('site.landing.ctaConsult')}
            </Link>
            <Link
              href={siteHref(undefined, 'portfolio')}
              className="rounded-full border border-white/15 bg-white/5 px-7 py-3.5 text-sm font-medium text-white/90 backdrop-blur hover:bg-white/10"
            >
              {t('site.landing.ctaWork')}
            </Link>
          </div>
        </div>
      </section>

      <section className="border-y border-white/10 bg-white/[0.02]">
        <div className="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-12 sm:grid-cols-4 lg:px-6">
          {STATS.map((s) => (
            <div key={s.key} className="reveal">
              <p className="text-3xl font-black text-white md:text-4xl">
                <span data-count={s.n}>0</span>
                <span className="text-[#0066FF]">{t(`site.landing.stat.${s.key}Suffix`)}</span>
              </p>
              <p className="mt-2 text-sm text-white/55">{t(`site.landing.stat.${s.key}`)}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-20 lg:px-6">
        <HeaderBlock title={t('site.landing.servicesTitle')} subtitle={t('site.landing.servicesLead')} href="services" more={t('site.home.allServices')} />
        <div className="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
          {SERVICE_MEGA.columns.map((col) => (
            <article key={col.titleKey} className="reveal group rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-transparent p-5 transition hover:border-[#0066FF]/50 hover:shadow-[0_20px_50px_-30px_#0066FF]">
              <h3 className="text-lg font-semibold text-white">{t(col.titleKey)}</h3>
              <ul className="mt-4 space-y-2 text-sm text-white/60">
                {col.items.slice(0, 5).map((item) => (
                  <li key={item.href}>
                    <Link href={siteHref(undefined, item.href)} className="hover:text-white">
                      {t(item.labelKey)}
                    </Link>
                  </li>
                ))}
              </ul>
            </article>
          ))}
        </div>
      </section>

      <section className="bg-[#0b1020] py-20">
        <div className="mx-auto max-w-7xl px-4 lg:px-6">
          <HeaderBlock title={t('site.landing.solutionsTitle')} subtitle={t('site.landing.solutionsLead')} href="solutions" more={t('site.nav.solutions')} />
          <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            {SOLUTION_MEGA.columns.map((col) => (
              <Link
                key={col.titleKey}
                href={siteHref(undefined, col.href || 'solutions')}
                className="reveal rounded-2xl border border-white/10 bg-white/[0.03] p-5 transition hover:-translate-y-1 hover:border-[#0066FF]/60"
              >
                <h3 className="font-semibold text-white">{t(col.titleKey)}</h3>
                <p className="mt-3 text-sm leading-6 text-white/55">
                  {col.items.map((i) => t(i.labelKey)).join(' · ')}
                </p>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-20 lg:px-6">
        <HeaderBlock title={t('site.landing.valueTitle')} subtitle={t('site.landing.valueLead')} />
        <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-5">
          {VALUES.map((id) => (
            <article key={id} className="reveal rounded-2xl border border-white/10 p-5">
              <h3 className="font-semibold text-white">{t(`site.landing.value.${id}Title`)}</h3>
              <p className="mt-2 text-sm leading-6 text-white/60">{t(`site.landing.value.${id}Body`)}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="border-y border-white/10 bg-white/[0.02] py-20">
        <div className="mx-auto max-w-7xl px-4 lg:px-6">
          <HeaderBlock title={t('site.landing.segmentsTitle')} subtitle={t('site.landing.segmentsLead')} />
          <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {SEGMENTS.map((id) => (
              <article key={id} className="reveal rounded-2xl bg-gradient-to-br from-[#0066FF]/15 to-transparent p-6 ring-1 ring-white/10">
                <h3 className="text-lg font-semibold text-white">{t(`site.landing.seg.${id}Title`)}</h3>
                <p className="mt-2 text-sm text-white/60">{t(`site.landing.seg.${id}Body`)}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-20 lg:px-6">
        <HeaderBlock title={t('site.landing.processTitle')} subtitle={t('site.landing.processLead')} />
        <ol className="mt-10 grid gap-4 md:grid-cols-4">
          {PROCESS.map((id, i) => (
            <li key={id} className="reveal relative rounded-2xl border border-white/10 p-6">
              <span className="text-5xl font-black text-[#0066FF]/30">{String(i + 1).padStart(2, '0')}</span>
              <h3 className="mt-3 font-semibold text-white">{t(`site.landing.process.${id}Title`)}</h3>
              <p className="mt-2 text-sm text-white/60">{t(`site.landing.process.${id}Body`)}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="bg-[#0b1020] py-20">
        <div className="mx-auto max-w-7xl px-4 lg:px-6">
          <HeaderBlock title={t('site.landing.channelsTitle')} subtitle={t('site.landing.channelsLead')} />
          <div className="mt-10 grid gap-4 md:grid-cols-4">
            {CHANNELS.map((id) => (
              <article key={id} className="reveal rounded-2xl border border-white/10 p-5">
                <h3 className="font-semibold text-white">{t(`site.landing.ch.${id}Title`)}</h3>
                <p className="mt-2 text-sm text-white/60">{t(`site.landing.ch.${id}Body`)}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-20 lg:px-6">
        <HeaderBlock title={t('site.home.portfolio')} subtitle={t('site.landing.workLead')} href="portfolio" more={t('site.home.all')} />
        <div className="mt-10 grid gap-4 md:grid-cols-3">
          {(data?.portfolio?.length ? data.portfolio.slice(0, 6) : [1, 2, 3]).map((p, i) => {
            const item = typeof p === 'object' ? p : null;
            return (
              <Link
                key={item?.id ?? i}
                href={siteHref(undefined, item ? `portfolio/${item.slug}` : 'portfolio')}
                className="reveal group overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]"
              >
                <div className="h-40 bg-gradient-to-br from-[#0066FF]/40 via-[#0a0a0a] to-[#4d94ff]/20 transition duration-500 group-hover:scale-105" />
                <div className="p-5">
                  <h3 className="font-semibold text-white">{item?.title ?? t(`site.landing.case.${i + 1}Title`)}</h3>
                  <p className="mt-2 line-clamp-2 text-sm text-white/55">
                    {item?.description ?? t(`site.landing.case.${i + 1}Body`)}
                  </p>
                </div>
              </Link>
            );
          })}
        </div>
      </section>

      <section className="border-y border-white/10 bg-white/[0.02] py-20">
        <div className="mx-auto max-w-7xl px-4 lg:px-6">
          <HeaderBlock title={t('site.home.testimonials')} subtitle={t('site.landing.quotesLead')} />
          <div className="mt-10 grid gap-4 md:grid-cols-3">
            {data?.testimonials?.length
              ? data.testimonials.slice(0, 3).map((q) => (
                  <blockquote key={q.id} className="reveal rounded-2xl border border-white/10 p-6 text-sm text-white/75">
                    “{q.quote}”
                    <footer className="mt-4 font-medium text-white">
                      {q.author}
                      {q.company ? ` — ${q.company}` : ''}
                    </footer>
                  </blockquote>
                ))
              : FALLBACK_QUOTES.map((q) => (
                  <blockquote key={q.quoteKey} className="reveal rounded-2xl border border-white/10 p-6 text-sm text-white/75">
                    “{t(`site.landing.quotes.${q.quoteKey}`)}”
                    <footer className="mt-4 font-medium text-white">
                      {t(`site.landing.quotes.${q.authorKey}`)}
                      <span className="block text-xs font-normal text-white/45">{t(`site.landing.quotes.${q.roleKey}`)}</span>
                    </footer>
                  </blockquote>
                ))}
          </div>
        </div>
      </section>

      {(data?.blog?.length ?? 0) > 0 ? (
        <section className="mx-auto max-w-7xl px-4 py-20 lg:px-6">
          <HeaderBlock title={t('site.home.blog')} href="blog" more={t('site.nav.blog')} />
          <ul className="mt-10 grid gap-4 md:grid-cols-3">
            {data!.blog!.slice(0, 3).map((b) => (
              <li key={b.id} className="reveal">
                <Link href={siteHref(undefined, `blog/${b.slug}`)} className="block rounded-2xl border border-white/10 p-5 hover:border-[#0066FF]/40">
                  <h3 className="font-medium text-white">{b.title}</h3>
                  {b.excerpt ? <p className="mt-2 line-clamp-2 text-sm text-white/55">{b.excerpt}</p> : null}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <section className="relative overflow-hidden px-4 py-24">
        <div className="orb -start-10 top-0 size-72 bg-[#0066FF]/30" />
        <div className="relative mx-auto max-w-4xl rounded-[2rem] border border-white/10 bg-gradient-to-br from-[#0066FF]/25 to-transparent px-6 py-16 text-center">
          <h2 className="reveal text-3xl font-black text-white md:text-5xl">{t('site.landing.finalTitle')}</h2>
          <p className="reveal mx-auto mt-4 max-w-xl text-white/65">{t('site.landing.finalLead')}</p>
          <div className="reveal mt-8 flex flex-wrap justify-center gap-3">
            <Link href={siteHref(undefined, 'consultation')} className="rounded-full bg-[#0066FF] px-7 py-3.5 text-sm font-semibold text-white">
              {t('site.nav.freeConsultation')}
            </Link>
            <Link href={siteHref(undefined, 'proposal')} className="inline-flex items-center gap-1 rounded-full border border-white/15 px-7 py-3.5 text-sm text-white">
              {t('site.nav.proposal')}
              <ArrowUpRight className="size-4" />
            </Link>
          </div>
        </div>
      </section>
    </article>
  );
}

function HeaderBlock({
  title,
  subtitle,
  href,
  more,
}: {
  title: string;
  subtitle?: string;
  href?: string;
  more?: string;
}) {
  return (
    <div className={cn('flex flex-col gap-3 md:flex-row md:items-end md:justify-between')}>
      <div className="reveal max-w-2xl">
        <h2 className="text-3xl font-bold tracking-tight text-white md:text-4xl">{title}</h2>
        {subtitle ? <p className="mt-3 text-white/55">{subtitle}</p> : null}
      </div>
      {href && more ? (
        <Link href={siteHref(undefined, href)} className="reveal text-sm text-[#6ea8ff] hover:underline">
          {more}
        </Link>
      ) : null}
    </div>
  );
}
