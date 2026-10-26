'use client';

import { useEffect, type RefObject } from 'react';

/**
 * Lightweight landing motion (no GSAP): CSS-driven hero intro, IntersectionObserver
 * reveals and rAF counters. Work is scheduled after first paint to keep TBT low.
 */
export function useLandingMotion(root: RefObject<HTMLElement | null>, locale = 'fa') {
  useEffect(() => {
    const el = root.current;
    if (!el || typeof IntersectionObserver === 'undefined') return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const fmt = new Intl.NumberFormat(locale === 'fa' ? 'fa-IR' : 'en-US');
    const raf: number[] = [];

    const count = (node: HTMLElement) => {
      const target = Number(node.dataset.count || 0);
      const start = performance.now();
      const step = (now: number) => {
        const p = Math.min(1, (now - start) / 1600);
        node.textContent = fmt.format(Math.round(target * (1 - (1 - p) * (1 - p))));
        if (p < 1) raf.push(requestAnimationFrame(step));
      };
      raf.push(requestAnimationFrame(step));
    };

    const io = new IntersectionObserver(
      (entries) => {
        for (const e of entries) {
          if (!e.isIntersecting) continue;
          const t = e.target as HTMLElement;
          io.unobserve(t);
          if (t.dataset.count !== undefined) count(t);
          else t.classList.add('is-in');
        }
      },
      { rootMargin: '0px 0px -12% 0px' },
    );

    const below = (n: HTMLElement) => n.getBoundingClientRect().top > window.innerHeight;
    const idle = (window as unknown as { requestIdleCallback?: (cb: () => void) => number }).requestIdleCallback ?? ((cb: () => void) => window.setTimeout(cb, 1));
    idle(() => {
      el.querySelectorAll<HTMLElement>('.reveal').forEach((n) => {
        if (below(n)) {
          n.classList.add('reveal-pending');
          io.observe(n);
        }
      });
      el.querySelectorAll<HTMLElement>('[data-count]').forEach((n) => io.observe(n));
    });

    return () => {
      io.disconnect();
      raf.forEach((id) => cancelAnimationFrame(id));
    };
  }, [root, locale]);
}
