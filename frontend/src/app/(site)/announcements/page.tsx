import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type AnnouncementItem = { id: number; title: string; body?: string | null };

export default async function AnnouncementsPage() {
  const t = await getTranslations();
  let items: AnnouncementItem[] = [];
  try {
    const res = await apiServer<{ data: AnnouncementItem[] }>('/v1/public/announcements');
    items = res.data ?? [];
  } catch {
    items = [];
  }

  return (
    <SitePage kicker={t('site.nav.resources')} title={t('site.home.announcements')} lead={t('site.page.announcementsLead')}>
      {items.length ? (
        <ul className="space-y-3">
          {items.map((a) => (
            <li key={a.id} className="panel">
              <h2 className="font-bold">{a.title}</h2>
              {a.body ? <p className="muted mt-2 text-sm leading-7">{a.body}</p> : null}
            </li>
          ))}
        </ul>
      ) : (
        <EmptyNote>{t('site.page.emptyCms')}</EmptyNote>
      )}
    </SitePage>
  );
}
