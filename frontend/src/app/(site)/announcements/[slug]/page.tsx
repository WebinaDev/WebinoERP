import { breadcrumbLd, itemMetadata, JsonLd, siteOrigin } from '@/lib/seo';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { apiServer } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type Ann = { title: string; summary?: string | null; body?: string | null; type?: string | null; starts_at?: string | null; created_at?: string | null };
const TYPES: Record<string, string> = { event: 'رویداد', update: 'به‌روزرسانی', important: 'مهم', news: 'خبر سازمانی' };

async function load(slug: string): Promise<Ann | null> {
  try {
    return (await apiServer<{ data: Ann }>(`/v1/public/announcements/${encodeURIComponent(slug)}`)).data;
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return itemMetadata<Record<string, unknown>>(`/v1/public/announcements/${encodeURIComponent(slug)}`, `/announcements/${slug}`, (d) => ({ title: d.title as string, description: (d.summary || d.body) as string, type: 'article' as const, publishedTime: (d.starts_at || d.created_at) as string }));
}

export default async function AnnouncementPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const a = await load(slug);
  if (!a) notFound();
  const origin = await siteOrigin();
  const when = a.starts_at || a.created_at;
  return (
    <SitePage kicker={(a.type && TYPES[a.type]) || 'اطلاعیه'} title={a.title} lead={a.summary || undefined}>
      <JsonLd data={breadcrumbLd(origin, [{ name: 'خانه', path: '/' }, { name: 'اطلاعیه‌ها', path: '/announcements' }, { name: a.title, path: `/announcements/${slug}` }])} />
      {when ? <p className="mb-6 text-sm opacity-70">{new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'long' }).format(new Date(when))}</p> : null}
      <div className="rich-copy" dangerouslySetInnerHTML={{ __html: a.body ?? '' }} />
      <Link href="/announcements" className="mt-8 inline-block text-sm font-bold text-[var(--saffron-deep)]">همه اطلاعیه‌ها</Link>
    </SitePage>
  );
}
