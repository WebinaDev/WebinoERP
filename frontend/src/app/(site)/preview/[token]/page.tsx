import type { Metadata } from 'next';
import Link from 'next/link';
import { StorefrontDocument } from '@/builder/public-document';
import { isDocument } from '@/builder/tree';
import { apiServer } from '@/lib/api-server';
import { getServerTranslations } from '@/lib/server-translations';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = { robots: { index: false, follow: false } };

type PreviewPayload = { data?: { title?: string; document?: unknown; lang?: string | null } };

/** Draft preview through an expiring token link created in the builder (noindex, never cached). */
export default async function PreviewPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  const t = await getServerTranslations('site');
  const res = /^\d+\.[a-f0-9]{48}$/.test(token)
    ? await apiServer<PreviewPayload>(`/api/v1/public/builder/preview/${token}`, { revalidate: 0 }).catch(() => null)
    : null;
  const document = res?.data?.document;
  if (!isDocument(document)) {
    return (
      <div className="container mx-auto max-w-xl px-4 py-20 text-center">
        <h1 className="text-xl font-semibold">{t('preview_expired')}</h1>
        <Link href="/" className="mt-6 inline-block text-sm underline">{t('back_home')}</Link>
      </div>
    );
  }
  return (
    <>
      <div className="sticky top-0 z-40 bg-amber-100 px-4 py-2 text-center text-xs text-amber-900" role="status">{t('preview_banner')}</div>
      <StorefrontDocument document={document} runtime={{ siteName: res?.data?.title ?? '', lang: res?.data?.lang ?? null }} />
    </>
  );
}
