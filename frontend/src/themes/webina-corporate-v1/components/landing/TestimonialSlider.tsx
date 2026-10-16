'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, Quote, Star } from 'lucide-react';

export type TestimonialItem = {
  id: number;
  author: string;
  quote: string;
  company?: string | null;
  role?: string | null;
  avatar_url?: string | null;
  rating?: number | null;
};

export function TestimonialSlider({ items }: { items: TestimonialItem[] }) {
  const track = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);
  const [paused, setPaused] = useState(false);

  const goTo = useCallback((i: number) => {
    const el = track.current;
    if (!el) return;
    const n = items.length;
    const idx = ((i % n) + n) % n;
    const card = el.children[idx] as HTMLElement | undefined;
    if (card) {
      const er = el.getBoundingClientRect();
      const cr = card.getBoundingClientRect();
      el.scrollBy({ left: cr.left + cr.width / 2 - (er.left + er.width / 2), behavior: 'smooth' });
    }
    setActive(idx);
  }, [items.length]);

  useEffect(() => {
    const el = track.current;
    if (!el) return;
    const onScroll = () => {
      const center = el.getBoundingClientRect().left + el.clientWidth / 2;
      let best = 0;
      let dist = Infinity;
      Array.from(el.children).forEach((c, i) => {
        const r = (c as HTMLElement).getBoundingClientRect();
        const d = Math.abs(r.left + r.width / 2 - center);
        if (d < dist) { dist = d; best = i; }
      });
      setActive(best);
    };
    el.addEventListener('scroll', onScroll, { passive: true });
    return () => el.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    if (paused || items.length < 2) return;
    const reduce = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;
    const t = window.setInterval(() => goTo(active + 1), 6500);
    return () => window.clearInterval(t);
  }, [active, paused, goTo, items.length]);

  return (
    <div
      className="tslider"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onTouchStart={() => setPaused(true)}
      aria-roledescription="carousel"
    >
      <div className="tslider-track" ref={track}>
        {items.map((q, i) => (
          <figure key={q.id} className={i === active ? 'tcard is-active' : 'tcard'} aria-roledescription="slide">
            <Quote className="tcard-mark" aria-hidden />
            <div className="tcard-stars" aria-label={`${q.rating ?? 5} از ۵`}>
              {Array.from({ length: 5 }).map((_, s) => (
                <Star key={s} className={s < (q.rating ?? 5) ? 'on' : ''} aria-hidden />
              ))}
            </div>
            <blockquote>{q.quote}</blockquote>
            <figcaption>
              <span className="tcard-avatar">
                {q.avatar_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={q.avatar_url} alt={q.author} loading="lazy" />
                ) : (
                  <span>{q.author.trim().charAt(0)}</span>
                )}
              </span>
              <span className="tcard-who">
                <strong>{q.author}</strong>
                {q.role || q.company ? <small>{[q.role, q.company].filter(Boolean).join('، ')}</small> : null}
              </span>
            </figcaption>
          </figure>
        ))}
      </div>
      {items.length > 1 ? (
        <div className="tslider-nav">
          <button type="button" className="tslider-btn" aria-label="قبلی" onClick={() => goTo(active - 1)}>
            <ChevronRight aria-hidden />
          </button>
          <div className="tslider-dots">
            {items.map((q, i) => (
              <button
                key={q.id}
                type="button"
                aria-label={`نظر ${i + 1}`}
                className={i === active ? 'on' : ''}
                onClick={() => goTo(i)}
              />
            ))}
          </div>
          <button type="button" className="tslider-btn" aria-label="بعدی" onClick={() => goTo(active + 1)}>
            <ChevronLeft aria-hidden />
          </button>
        </div>
      ) : null}
    </div>
  );
}
