import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type DownloadItem = { id: number; title: string; file?: { public_url?: string | null } | null };

export default async function DownloadsPage() {
  const t = await getTranslations();
  let items: DownloadItem[] = [];
  try {
    const res = await apiServer<{ data: DownloadItem[] }>('/v1/public/downloads');
    items = res.data ?? [];
  } catch {
    items = [];
  }

  return (
    <SitePage kicker={t('site.nav.resources')} title={t('site.nav.downloads')} lead={t('site.page.downloadsLead')}>
      {items.length ? (
        <ul className="space-y-3">
          {items.map((d) => (
            <li key={d.id} className="panel flex items-center justify-between gap-3">
              <span className="font-semibold">{d.title}</span>
              {d.file?.public_url ? (
                <a href={d.file.public_url} className="text-sm font-bold text-[var(--saffron-deep)]" download>
                  {t('auto._site__downloads_page.s_2333a0ea')}
                </a>
              ) : null}
            </li>
          ))}
        </ul>
      ) : (
        <EmptyNote>{t('site.page.downloadsEmpty')}</EmptyNote>
      )}
    </SitePage>
  );
}
