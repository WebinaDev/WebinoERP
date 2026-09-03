import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';

export const revalidate = 60;

type AnnouncementItem = { id: number; title: string; body?: string | null };

export default async function AnnouncementsPage() {
  const t = await getTranslations();

  let items: AnnouncementItem[] = [];
  try {
    const res = await apiServer<{ data: AnnouncementItem[] }>('/v1/public/announcements');
    items = res.data ?? [];
  } catch {
    /* empty */
  }

  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{t('auto._site__announcements_page.s_f566280c')}</h1>
      <ul className="mt-8 space-y-4">
        {items.map((a) => (
          <li key={a.id} className="rounded-xl border p-5">
            <h2 className="font-semibold">{a.title}</h2>
            {a.body ? <p className="text-muted-foreground mt-2 text-sm">{a.body}</p> : null}
          </li>
        ))}
      </ul>
    </div>
  );
}
