import { BrandMark, BrandWordmark } from './BrandMark';

/** Logo URLs that point at the bundled brand assets; the official vector logo replaces them. */
const BUNDLED_LOGOS = new Set(['', '/brand/logo.png', '/brand/webina-logo.svg']);

/**
 * Header: official mark + wordmark drawn inline (charcoal follows currentColor).
 * With a tagline (footer): the official full lockup, mark + wordmark + «آژانس توسعه کسب و کار».
 * Proportions follow the Illustrator master: the mark is ~1.4x the wordmark height.
 */
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

  if (tagline) {
    return (
      <span className="logo-lockup">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src="/brand/webina-logo.svg"
          alt={`${siteName} — ${tagline}`}
          width={2666}
          height={971}
          className="h-14 w-auto max-w-full"
        />
      </span>
    );
  }

  return (
    <span className="logo-lockup">
      <BrandMark className={compact ? 'h-8 w-auto shrink-0' : 'h-9 w-auto shrink-0'} />
      <BrandWordmark className={compact ? 'h-[1.4rem] w-auto' : 'h-[1.6rem] w-auto'} />
      <span className="sr-only">{siteName}</span>
    </span>
  );
}
