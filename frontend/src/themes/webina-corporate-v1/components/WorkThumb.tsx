/**
 * Abstract geometric covers for portfolio cards. They echo the Webina mark
 * (rounded hook, diagonal bars) in blue #0054ff, charcoal #22242a and white,
 * so case studies without a real cover still look intentional.
 */
const BLUE = '#0054ff';
const INK = '#22242a';
const TINT = '#f2f6ff';
const TINT2 = '#e3ebff';

const DARK_VARIANTS = new Set([1, 2, 5]);

export function isDarkThumb(index: number) {
  return DARK_VARIANTS.has(index % 6);
}

/** Seeded placeholder covers are replaced by the geometric art; real uploads are shown as-is. */
export function realCover(url?: string | null) {
  if (!url) return null;
  return url.startsWith('/marketing/case-') ? null : url;
}

function Bars({ x, y, color, w = 120, gap = 34, h = 16 }: { x: number; y: number; color: string; w?: number; gap?: number; h?: number }) {
  return (
    <g fill={color}>
      {[0, 1, 2].map((i) => (
        <path key={i} d={`M${x + i * 14} ${y + i * gap} h${w - i * 18} l-14 ${h} h-${w - i * 18} z`} />
      ))}
    </g>
  );
}

export function WorkThumb({ index }: { index: number }) {
  const v = index % 6;
  return (
    <svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" aria-hidden xmlns="http://www.w3.org/2000/svg">
      {v === 0 ? (
        <>
          <rect width="400" height="300" fill={TINT} />
          <path d="M120 40 h120 a90 90 0 0 1 90 90 v170" fill="none" stroke={BLUE} strokeWidth="34" />
          <path d="M170 92 h66 a40 40 0 0 1 40 40 v168" fill="none" stroke={INK} strokeWidth="22" />
          <Bars x={40} y={200} color={BLUE} />
        </>
      ) : null}
      {v === 1 ? (
        <>
          <rect width="400" height="300" fill={BLUE} />
          {Array.from({ length: 6 }).map((_, r) =>
            Array.from({ length: 8 }).map((__, c) => (
              <circle
                key={`${r}-${c}`}
                cx={40 + c * 46}
                cy={36 + r * 46}
                r={r === 2 && c === 5 ? 20 : 4}
                fill="#fff"
                opacity={r === 2 && c === 5 ? 1 : 0.55}
              />
            )),
          )}
        </>
      ) : null}
      {v === 2 ? (
        <>
          <rect width="400" height="300" fill={INK} />
          <rect x="210" y="0" width="190" height="300" fill={BLUE} />
          <circle cx="210" cy="150" r="70" fill="none" stroke="#fff" strokeWidth="18" />
          <Bars x={36} y={60} color="#fff" w={110} gap={30} h={14} />
        </>
      ) : null}
      {v === 3 ? (
        <>
          <rect width="400" height="300" fill="#fff" />
          {[0, 1, 2, 3, 4].map((i) => (
            <circle key={i} cx="400" cy="300" r={60 + i * 52} fill="none" stroke={i === 2 ? BLUE : TINT2} strokeWidth={i === 2 ? 22 : 18} />
          ))}
          <rect x="40" y="40" width="56" height="56" rx="10" fill={INK} />
        </>
      ) : null}
      {v === 4 ? (
        <>
          <rect width="400" height="300" fill={TINT2} />
          <circle cx="140" cy="160" r="92" fill={BLUE} />
          <rect x="196" y="72" width="150" height="150" rx="24" fill="none" stroke={INK} strokeWidth="16" />
          <rect x="300" y="226" width="60" height="16" rx="8" fill={INK} />
        </>
      ) : null}
      {v === 5 ? (
        <>
          <rect width="400" height="300" fill={BLUE} />
          <path d="M-20 260 C 80 260 120 60 220 60 S 360 160 420 140" fill="none" stroke={INK} strokeWidth="26" />
          <path d="M-20 300 C 80 300 140 110 240 110 S 380 210 420 190" fill="none" stroke="#fff" strokeWidth="8" opacity=".75" />
          <Bars x={260} y={210} color="#fff" w={100} gap={26} h={12} />
        </>
      ) : null}
    </svg>
  );
}
