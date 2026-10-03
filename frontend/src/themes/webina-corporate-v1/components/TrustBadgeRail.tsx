'use client';

import { useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export type TrustBadge = {
  id: string;
  label: string;
  hint: string;
  href?: string | null;
  image?: string | null;
};

function Seal({ id }: { id: string }) {
  const glyph = id === 'ssl' ? 'SSL' : id === 'hosting' ? 'SRV' : id === 'samandehi' ? 'IR' : 'نماد';
  return (
    <svg className="seal" viewBox="0 0 64 64" aria-hidden>
      <rect x="2" y="2" width="60" height="60" rx="14" fill="#16130f" stroke="#e4c27a" strokeWidth="2" />
      <circle cx="32" cy="26" r="10" fill="none" stroke="#e4c27a" strokeWidth="2" />
      <text x="32" y="30" textAnchor="middle" fontSize="8" fill="#e4c27a" fontFamily="inherit">
        {glyph}
      </text>
      <text x="32" y="50" textAnchor="middle" fontSize="7" fill="#f6f1e8" fontFamily="inherit">
        PLACEHOLDER
      </text>
    </svg>
  );
}

export function TrustBadgeRail({
  badges,
  title,
  lead,
  prevLabel,
  nextLabel,
}: {
  badges: TrustBadge[];
  title: string;
  lead: string;
  prevLabel: string;
  nextLabel: string;
}) {
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const [perView, setPerView] = useState(1);
  const [rtl, setRtl] = useState(true);

  useEffect(() => {
    const sync = () => {
      setPerView(window.innerWidth >= 800 ? 3 : 1);
      setRtl(document.documentElement.dir !== 'ltr');
    };
    sync();
    window.addEventListener('resize', sync);
    return () => window.removeEventListener('resize', sync);
  }, []);

  const max = Math.max(1, badges.length);
  const step = (dir: number) => setIndex((i) => (i + dir + max) % max);

  useEffect(() => {
    if (paused || badges.length < 2) return;
    const id = window.setInterval(() => step(1), 3800);
    return () => window.clearInterval(id);
  }, [paused, badges.length, max]);

  const visible = Array.from({ length: Math.min(perView, badges.length) }, (_, offset) => badges[(index + offset) % badges.length]);
  const PrevIcon = rtl ? ChevronRight : ChevronLeft;
  const NextIcon = rtl ? ChevronLeft : ChevronRight;

  return (
    <div
      className="trust-wrap"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
    >
      <div className="webina-shell">
        <div className="trust-head">
          <div>
            <h2>{title}</h2>
            <p className="mt-1 max-w-xl text-sm" style={{ color: 'rgba(246,241,232,.62)' }}>{lead}</p>
          </div>
          <div className="trust-nav">
            <button type="button" aria-label={prevLabel} onClick={() => step(-1)}>
              <PrevIcon className="mx-auto size-4" />
            </button>
            <button type="button" aria-label={nextLabel} onClick={() => step(1)}>
              <NextIcon className="mx-auto size-4" />
            </button>
          </div>
        </div>
        <div className="trust-viewport" aria-roledescription="carousel">
          <div className="trust-row">
            {visible.map((badge) => {
              const inner = (
                <>
                  {badge.image ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={badge.image} alt="" className="seal object-contain" />
                  ) : (
                    <Seal id={badge.id} />
                  )}
                  <span>
                    <strong className="text-white">{badge.label}</strong>
                    <small>{badge.hint}</small>
                  </span>
                </>
              );
              return badge.href ? (
                <a key={badge.id} href={badge.href} className="trust-card" target="_blank" rel="noreferrer">
                  {inner}
                </a>
              ) : (
                <article key={badge.id} className="trust-card">
                  {inner}
                </article>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
