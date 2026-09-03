import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { LanguageMenu } from '@/components/LanguageMenu';
import { siteHref } from '@/lib/public-api-server';

type NavItem = { href: string; label: string };

const MAIN_NAV: NavItem[] = [
  { href: '', label: 'خانه' },
  { href: 'services', label: 'خدمات' },
  { href: 'solutions', label: 'راهکارها' },
  { href: 'portfolio', label: 'نمونه‌کارها' },
  { href: 'blog', label: 'بلاگ' },
  { href: 'academy', label: 'آکادمی' },
  { href: 'about', label: 'درباره ما' },
  { href: 'contact', label: 'تماس' },
];

export function SiteHeader({
  siteName,
  logoUrl,
}: {
  siteName: string;
  logoUrl?: string | null;
}) {
  return (
    <header className="sticky top-0 z-50 border-b border-white/10 bg-[#0a0a0a]/95 text-white backdrop-blur">
      <div className="container mx-auto flex h-16 items-center justify-between gap-4 px-4">
        <Link href={siteHref()} className="flex items-center gap-3">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt={siteName} width={36} height={36} className="size-9 rounded-md object-contain" />
          ) : null}
          <span className="text-lg font-semibold tracking-tight">{siteName}</span>
        </Link>
        <nav className="hidden items-center gap-1 lg:flex">
          {MAIN_NAV.map((item) => (
            <Button key={item.href || 'home'} variant="ghost" size="sm" asChild className="text-white/90 hover:text-white">
              <Link href={siteHref(undefined, item.href)}>{item.label}</Link>
            </Button>
          ))}
        </nav>
        <div className="flex items-center gap-2">
          <LanguageMenu />
          <Button size="sm" asChild className="bg-[#0066FF] hover:bg-[#0052cc]">
            <Link href={siteHref(undefined, 'consultation')}>مشاوره رایگان</Link>
          </Button>
        </div>
      </div>
    </header>
  );
}
