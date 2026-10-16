'use client';

import type { CSSProperties } from 'react';

// Client-side variant of NavIcon. `import * as Lucide` in a client component ships
// every lucide icon (about 900 KB) to every public page. Instead the icon is drawn
// with a CSS mask over a cached SVG from /lucide-icon/<Name>.svg, so it still
// follows the text colour and any icon name chosen in the admin keeps working.
function toPascal(name: string): string {
  const raw = (name || '').trim().replace(/Icon$/, '');
  if (!raw) return '';
  if (/^[A-Z][A-Za-z0-9]*$/.test(raw)) return raw;
  return raw
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join('');
}

export function NavIcon({ name, className = 'size-4' }: { name: string; className?: string }) {
  const icon = toPascal(name);
  if (!icon) return <span className={className} aria-hidden />;
  const url = `url("/lucide-icon/${icon}.svg")`;
  const style: CSSProperties = {
    display: 'inline-block',
    flexShrink: 0,
    backgroundColor: 'currentColor',
    WebkitMask: `${url} center / contain no-repeat`,
    mask: `${url} center / contain no-repeat`,
  };
  return <span className={className} style={style} aria-hidden />;
}
