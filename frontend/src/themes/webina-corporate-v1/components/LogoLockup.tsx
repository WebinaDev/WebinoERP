import { BrandMark, BrandWordmark } from './BrandMark';

/** Logo URLs that point at the bundled brand assets; the vector lockup replaces them. */
const BUNDLED_LOGOS = new Set(['', '/brand/logo.png', '/brand/webina-logo.svg']);

export function LogoLockup({
  siteName,
  logoUrl,
  compact = false,
  tagline,
}: {
  siteName: string;
  logoUrl?: string | null;
  compact?: boolean;
  tagline?: string;
}) {
  const custom = logoUrl && !BUNDLED_LOGOS.has(logoUrl) ? logoUrl : null;

  if (custom) {
    return (
      <span className="logo-lockup">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={custom} alt={siteName} className="h-10 w-auto max-w-[9rem] object-contain" />
      </span>
    );
  }

  return (
    <span className="logo-lockup">
      <BrandMark className={compact ? 'h-9 w-auto shrink-0' : 'h-10 w-auto shrink-0'} />
      <span className="flex flex-col items-start">
        <BrandWordmark className={compact ? 'h-[1.35rem] w-auto' : 'h-6 w-auto'} />
        {tagline ? <small className="logo-tagline">{tagline}</small> : null}
      </span>
      <span className="sr-only">{siteName}</span>
    </span>
  );
}
