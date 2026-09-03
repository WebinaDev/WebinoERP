'use client';

import { useLayoutEffect, type RefObject } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

let registered = false;

export function useLandingMotion(root: RefObject<HTMLElement | null>, locale = 'fa') {
  useLayoutEffect(() => {
    if (!root.current) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (!registered) {
      gsap.registerPlugin(ScrollTrigger);
      registered = true;
    }

    const ctx = gsap.context(() => {
      gsap.from('.hero-kicker, .hero-title span, .hero-lead, .hero-cta', {
        y: 40,
        opacity: 0,
        duration: 0.9,
        stagger: 0.06,
        ease: 'power3.out',
      });

      gsap.to('.orb-a', { x: 80, y: 40, duration: 8, repeat: -1, yoyo: true, ease: 'sine.inOut' });
      gsap.to('.orb-b', { x: -60, y: -30, duration: 10, repeat: -1, yoyo: true, ease: 'sine.inOut' });

      gsap.utils.toArray<HTMLElement>('.reveal').forEach((el) => {
        gsap.fromTo(
          el,
          { y: 36, opacity: 0 },
          {
            y: 0,
            opacity: 1,
            duration: 0.8,
            ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 86%' },
          },
        );
      });

      gsap.utils.toArray<HTMLElement>('[data-count]').forEach((el) => {
        const target = Number(el.dataset.count || 0);
        const obj = { n: 0 };
        gsap.to(obj, {
          n: target,
          duration: 1.6,
          ease: 'power2.out',
          scrollTrigger: { trigger: el, start: 'top 88%' },
          onUpdate: () => {
            el.textContent = Math.round(obj.n).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US');
          },
        });
      });
    }, root);

    return () => ctx.revert();
  }, [root, locale]);
}
