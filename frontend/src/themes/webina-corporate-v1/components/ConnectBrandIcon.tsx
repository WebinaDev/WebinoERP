type BrandKind =
  | 'phone'
  | 'instagram'
  | 'telegram'
  | 'whatsapp'
  | 'linkedin'
  | 'web'
  | 'bale'
  | 'eitaa'
  | 'rubika'
  | 'gap'
  | 'igap';

const BRAND: Record<
  BrandKind,
  { src?: string; color: string; label: string; svg?: 'phone' | 'web' | 'instagram' | 'telegram' | 'whatsapp' | 'linkedin' }
> = {
  phone: { color: '#0054ff', label: 'تلفن', svg: 'phone' },
  web: { color: '#0054ff', label: 'وب‌سایت', svg: 'web' },
  instagram: { src: '/brand/connect/instagram.svg', color: '#E4405F', label: 'اینستاگرام', svg: 'instagram' },
  telegram: { src: '/brand/connect/telegram.svg', color: '#26A5E4', label: 'تلگرام', svg: 'telegram' },
  whatsapp: { src: '/brand/connect/whatsapp.svg', color: '#25D366', label: 'واتساپ', svg: 'whatsapp' },
  linkedin: { src: '/brand/connect/linkedin.svg', color: '#0A66C2', label: 'لینکدین', svg: 'linkedin' },
  bale: { src: '/brand/connect/bale.png', color: '#00A651', label: 'بله' },
  eitaa: { src: '/brand/connect/eitaa.png', color: '#FF7534', label: 'ایتا' },
  rubika: { src: '/brand/connect/rubika.png', color: '#7B2D8E', label: 'روبیکا' },
  gap: { src: '/brand/connect/gap.png', color: '#1E88E5', label: 'گپ' },
  igap: { src: '/brand/connect/igap.png', color: '#00BFA5', label: 'آیگپ' },
};

function PhoneGlyph() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden className="ch-glyph">
      <path
        fill="currentColor"
        d="M6.62 10.79a15.15 15.15 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1V21a1 1 0 01-1 1C10.4 22 2 13.6 2 3a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"
      />
    </svg>
  );
}

function WebGlyph() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden className="ch-glyph">
      <path
        fill="currentColor"
        d="M12 2a10 10 0 100 20 10 10 0 000-20zm7.93 9h-3.17a15.4 15.4 0 00-1.2-5.04A8.03 8.03 0 0119.93 11zM12 4c.9 0 2.23 1.74 2.9 5H9.1C9.77 5.74 11.1 4 12 4zM4.07 13h3.17c.2 1.8.66 3.5 1.2 5.04A8.03 8.03 0 014.07 13zM7.24 11H4.07a8.03 8.03 0 014.37-5.04A15.4 15.4 0 007.24 11zM9.1 13h5.8c-.67 3.26-2 5-2.9 5s-2.23-1.74-2.9-5zm5.66 5.04c.54-1.54 1-3.24 1.2-5.04h3.17a8.03 8.03 0 01-4.37 5.04z"
      />
    </svg>
  );
}

/** Official brand mark for a channel (Simple Icons SVG or favicon-derived PNG). */
export function ConnectBrandIcon({ kind }: { kind: BrandKind }) {
  const meta = BRAND[kind];
  const style = { ['--ch-brand' as string]: meta.color };

  if (meta.svg === 'phone') {
    return (
      <span className="ch-brand-icon is-solid" style={style} aria-hidden>
        <PhoneGlyph />
      </span>
    );
  }
  if (meta.svg === 'web') {
    return (
      <span className="ch-brand-icon is-solid" style={style} aria-hidden>
        <WebGlyph />
      </span>
    );
  }

  // Monochrome SVGs tinted with official brand color
  if (meta.svg && meta.src?.endsWith('.svg')) {
    return (
      <span className="ch-brand-icon is-tint" style={style} aria-hidden>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={meta.src} alt="" width={22} height={22} />
      </span>
    );
  }

  return (
    <span className="ch-brand-icon is-photo" style={style} aria-hidden>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={meta.src} alt="" width={28} height={28} />
    </span>
  );
}

export type { BrandKind };
export { BRAND as CONNECT_BRAND_META };
