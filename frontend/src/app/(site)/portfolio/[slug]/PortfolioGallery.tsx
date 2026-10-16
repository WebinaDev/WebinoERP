'use client';

import { useCallback, useEffect, useState } from 'react';

/** Main image + thumbnails; click opens a simple lightbox (Esc / arrows close and navigate). */
export function PortfolioGallery({ images, title }: { images: string[]; title: string }) {
  const [active, setActive] = useState(0);
  const [open, setOpen] = useState(false);
  const n = images.length;
  const go = useCallback((d: number) => setActive((i) => (i + d + n) % n), [n]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
      if (e.key === 'ArrowLeft') go(1);
      if (e.key === 'ArrowRight') go(-1);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, go]);

  if (!n) return null;
  return (
    <div className="space-y-3">
      <button type="button" onClick={() => setOpen(true)} aria-label="نمایش بزرگ تصویر"
        className="group relative block w-full overflow-hidden rounded-2xl border border-[#e6e9f2] bg-[#f5f8ff] shadow-sm">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={images[active]} alt={`${title} ${active + 1}`} className="aspect-[16/10] w-full object-cover object-top transition duration-500 group-hover:scale-[1.02]" />
        <span className="absolute bottom-3 left-3 rounded-full bg-[#22242a]/80 px-3 py-1 text-xs text-white">{active + 1} از {n}</span>
      </button>
      {n > 1 ? (
        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
          {images.map((src, i) => (
            <button key={src + i} type="button" onClick={() => setActive(i)} aria-label={`تصویر ${i + 1}`}
              className={`overflow-hidden rounded-xl border-2 transition ${i === active ? 'border-[#0054ff]' : 'border-transparent opacity-70 hover:opacity-100'}`}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={src} alt="" loading="lazy" className="aspect-[4/3] w-full object-cover object-top" />
            </button>
          ))}
        </div>
      ) : null}
      {open ? (
        <div role="dialog" aria-modal="true" className="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-4" onClick={() => setOpen(false)}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={images[active]} alt={`${title} ${active + 1}`} className="max-h-[90vh] max-w-full rounded-xl object-contain" onClick={(e) => e.stopPropagation()} />
          <button type="button" aria-label="بستن" onClick={() => setOpen(false)} className="absolute right-4 top-4 h-10 w-10 rounded-full bg-white/15 text-2xl text-white">×</button>
          {n > 1 ? (
            <>
              <button type="button" aria-label="قبلی" onClick={(e) => { e.stopPropagation(); go(-1); }} className="absolute right-4 top-1/2 h-12 w-12 -translate-y-1/2 rounded-full bg-white/15 text-2xl text-white">›</button>
              <button type="button" aria-label="بعدی" onClick={(e) => { e.stopPropagation(); go(1); }} className="absolute left-4 top-1/2 h-12 w-12 -translate-y-1/2 rounded-full bg-white/15 text-2xl text-white">‹</button>
            </>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
