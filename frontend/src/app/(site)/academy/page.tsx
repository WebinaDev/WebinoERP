import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { apiServer, siteHref } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type AcademyItem = { id: number; slug: string; title: string; description?: string | null };

export default async function AcademyPage() {
  const t = await getTranslations();
  let items: AcademyItem[] = [];
  try {
    const res = await apiServer<{ data: AcademyItem[] }>('/v1/public/academy');
    items = res.data ?? [];
  } catch {
    items = [];
  }

  return (
    <SitePage kicker={t('site.nav.resources')} title={t('site.nav.academy')} lead={t('site.page.academyLead')}>
      {items.length ? (
        <ul className="card-grid cols-2">
          {items.map((p) => (
            <li key={p.id}>
              <Link href={siteHref(undefined, `academy/${p.slug}`)} className="panel block">
                <h2 className="font-bold">{p.title}</h2>
                {p.description ? <p className="muted mt-2 text-sm">{p.description}</p> : null}
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
