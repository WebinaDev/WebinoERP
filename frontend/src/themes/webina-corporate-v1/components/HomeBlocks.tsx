import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { Button } from '@/components/ui/button';
import { siteHref } from '@/lib/public-api-server';

type HomeData = {
  site: { name: string; branding?: Record<string, unknown> | null };
  blocks: { type: string; enabled?: boolean }[];
  announcements: { id: number; title: string; body?: string | null }[];
  testimonials: { id: number; author: string; quote: string; company?: string | null }[];
  portfolio: { id: number; slug: string; title: string; description?: string | null }[];
  blog: { id: number; slug: string; title: string; excerpt?: string | null }[];
  services?: { id: number; slug: string; name: string }[];
} | null;

export async function HomeBlocks({ locale, data }: { locale: string; data: HomeData }) {
  const tr = await getTranslations();
  const name = data?.site?.name ?? tr('site.home.defaultName');
  const desc =
    (data?.site?.branding?.description as string | undefined) ??
    tr('site.home.defaultDesc');

  return (
    <>
      <section className="bg-gradient-to-b from-[#0066FF]/20 via-[#0a0a0a] to-background border-b">
        <div className="container mx-auto px-4 py-20 text-center md:py-28">
          <h1 className="text-4xl font-bold tracking-tight text-white md:text-5xl">{name}</h1>
          <p className="text-white/70 mx-auto mt-4 max-w-2xl text-lg">{desc}</p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Button asChild size="lg" className="bg-[#0066FF] hover:bg-[#0052cc]">
              <Link href={siteHref(undefined, 'consultation')}>{tr('site.home.start')}</Link>
            </Button>
            <Button asChild variant="outline" size="lg" className="border-white/30 text-white hover:bg-white/10">
              <Link href={siteHref(undefined, 'portfolio')}>{tr('site.home.viewPortfolio')}</Link>
            </Button>
          </div>
        </div>
      </section>

      {(data?.services?.length ?? 0) > 0 ? (
        <section className="container mx-auto px-4 py-16">
          <div className="flex items-end justify-between gap-4">
            <h2 className="text-2xl font-semibold">{tr('site.home.ourServices')}</h2>
            <Link href={siteHref(undefined, 'services')} className="text-[#0066FF] text-sm hover:underline">
              {tr('site.home.allServices')}
            </Link>
          </div>
          <div className="mt-6 grid gap-4 md:grid-cols-4">
            {data!.services!.slice(0, 8).map((s) => (
              <Link
                key={s.id}
                href={siteHref(undefined, `services/${s.slug}`)}
                className="rounded-xl border p-5 transition hover:border-[#0066FF]/50 hover:shadow-md"
              >
                <h3 className="font-medium">{s.name}</h3>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      {(data?.portfolio?.length ?? 0) > 0 ? (
        <section className="container mx-auto px-4 py-16">
          <div className="flex items-end justify-between gap-4">
            <h2 className="text-2xl font-semibold">{tr('site.home.portfolio')}</h2>
            <Link href={siteHref(undefined, 'portfolio')} className="text-[#0066FF] text-sm hover:underline">
              {tr('site.home.all')}
            </Link>
          </div>
          <div className="mt-6 grid gap-4 md:grid-cols-3">
            {data!.portfolio.slice(0, 3).map((p) => (
              <Link
                key={p.id}
                href={siteHref(undefined, `portfolio/${p.slug}`)}
                className="rounded-xl border p-5 transition hover:shadow-md"
              >
                <h3 className="font-medium">{p.title}</h3>
                {p.description ? (
                  <p className="text-muted-foreground mt-2 line-clamp-2 text-sm">{p.description}</p>
                ) : null}
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      {(data?.testimonials?.length ?? 0) > 0 ? (
        <section className="bg-muted/30 py-16">
          <div className="container mx-auto px-4">
            <h2 className="text-2xl font-semibold">{tr('site.home.testimonials')}</h2>
            <div className="mt-6 grid gap-4 md:grid-cols-2">
              {data!.testimonials.slice(0, 4).map((item) => (
                <blockquote key={item.id} className="rounded-xl border bg-background p-5 text-sm">
                  &ldquo;{item.quote}&rdquo;
                  <footer className="mt-3 font-medium">
                    {item.author}
                    {item.company ? ` — ${item.company}` : ''}
                  </footer>
                </blockquote>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {(data?.blog?.length ?? 0) > 0 ? (
        <section className="border-t py-16">
          <div className="container mx-auto px-4">
            <div className="flex items-end justify-between gap-4">
              <h2 className="text-2xl font-semibold">{tr('site.home.blog')}</h2>
              <Link href={siteHref(undefined, 'blog')} className="text-[#0066FF] text-sm hover:underline">
                {tr('site.nav.blog')}
              </Link>
            </div>
            <ul className="mt-6 grid gap-4 md:grid-cols-3">
              {data!.blog.map((b) => (
                <li key={b.id}>
                  <Link href={siteHref(undefined, `blog/${b.slug}`)} className="block rounded-xl border p-4 hover:shadow-sm">
                    <h3 className="font-medium">{b.title}</h3>
                    {b.excerpt ? (
                      <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">{b.excerpt}</p>
                    ) : null}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </section>
      ) : null}
    </>
  );
}
