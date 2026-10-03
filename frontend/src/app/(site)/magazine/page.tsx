import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type MagazineSummary = { id: number; slug: string; title: string; excerpt?: string | null };

export default async function MagazinePage() {
  const t = await getTranslations();
  let items: MagazineSummary[] = [];
  try {
    const res = await apiServer<{ data: MagazineSummary[] }>('/v1/public/magazine');
    items = res.data ?? [];
  } catch {
    items = [];
  }

  return (
    <SitePage kicker={t('site.nav.resources')} title={t('site.nav.magazine')} lead={t('site.page.magazineLead')}>
      {items.length ? (
        <ul className="card-grid cols-2">
          {items.map((p) => (
            <li key={p.id}>
              <Link href={siteHref(undefined, `magazine/${p.slug}`)} className="panel block">
                <h2 className="font-bold">{p.title}</h2>
                {p.excerpt ? <p className="muted mt-2 text-sm">{p.excerpt}</p> : null}
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyNote>{t('site.page.emptyCms')}</EmptyNote>
      )}
    </SitePage>
  );
}
