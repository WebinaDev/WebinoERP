import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';

export const revalidate = 60;

type DownloadItem = { id: number; title: string; category?: string | null; file?: { public_url?: string | null } | null };

export default async function DownloadsPage() {
  const t = await getTranslations();

  let items: DownloadItem[] = [];
  try {
    const res = await apiServer<{ data: DownloadItem[] }>('/v1/public/downloads');
    items = res.data ?? [];
  } catch {
    /* empty */
  }

  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{t('auto._site__downloads_page.s_5fa7d93f')}</h1>
      <ul className="mt-8 space-y-3">
        {items.map((d) => (
          <li key={d.id} className="flex items-center justify-between rounded-xl border px-5 py-4">
            <span>{d.title}</span>
            {d.file?.public_url ? (
              <a href={d.file.public_url} className="text-[#0066FF] text-sm hover:underline" download>{t('auto._site__downloads_page.s_2333a0ea')}</a>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}
