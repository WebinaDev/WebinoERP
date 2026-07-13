import { apiServer } from '@/src/lib/api-server';

export const revalidate = 60;

type DownloadItem = { id: number; title: string; category?: string | null; file?: { public_url?: string | null } | null };

export default async function DownloadsPage() {
  let items: DownloadItem[] = [];
  try {
    const res = await apiServer<{ data: DownloadItem[] }>('/v1/public/downloads');
    items = res.data ?? [];
  } catch {
    /* empty */
  }

  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">دانلودها</h1>
      <ul className="mt-8 space-y-3">
        {items.map((d) => (
          <li key={d.id} className="flex items-center justify-between rounded-xl border px-5 py-4">
            <span>{d.title}</span>
            {d.file?.public_url ? (
              <a href={d.file.public_url} className="text-[#0066FF] text-sm hover:underline" download>
                دانلود
              </a>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}
