import { itemMetadata } from '@/lib/seo';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { apiServer } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

/** Single portfolio item template (data from داشبورد ← بازاریابی ← نمونه‌کار; no content in code). */
type PortfolioItem = {
  title: string;
  description?: string | null;
  result_metric?: string | null;
  cover_url?: string | null;
  images?: string[] | null;
  technologies?: string[] | null;
  challenge?: string | null;
  solution?: string | null;
  results?: string | null;
  case_study?: string | null;
  category?: { name: string } | null;
};

function Block({ title, text }: { title: string; text?: string | null }) {
  if (!text?.trim()) return null;
  return (
    <section className="space-y-2">
      <h2 className="text-xl font-extrabold">{title}</h2>
      {text.split('\n').filter(Boolean).map((line, i) => (
        <p key={i} className="rich-copy leading-8">{line}</p>
      ))}
    </section>
  );
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return itemMetadata<Record<string, unknown>>(`/v1/public/portfolio/${encodeURIComponent(slug)}`, `/portfolio/${slug}`, (d) => ({ title: d.title as string, description: (d.excerpt || d.challenge || d.result_metric) as string, image: d.cover_url as string }));
}

export default async function PortfolioDetailPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  let item: PortfolioItem | null = null;
  try {
    item = (await apiServer<{ data: PortfolioItem }>(`/v1/public/portfolio/${encodeURIComponent(slug)}`)).data;
  } catch {
    item = null;
  }
  if (!item) notFound();

  const gallery = [item.cover_url, ...(item.images ?? [])].filter((src): src is string => !!src);
  const tags = item.technologies ?? [];

  return (
    <SitePage kicker={item.category?.name || 'نمونه‌کار'} title={item.title} lead={item.result_metric || item.description || undefined}>
      <div className="grid gap-10">
        {gallery.length ? (
          <div className={gallery.length > 1 ? 'grid gap-4 sm:grid-cols-2' : 'grid'}>
            {gallery.map((src, i) => (
              // eslint-disable-next-line @next/next/no-img-element
              <img key={i} src={src} alt={`${item.title} ${i + 1}`} loading={i ? 'lazy' : 'eager'}
                className={`w-full rounded-2xl object-cover ${i === 0 && gallery.length > 1 ? 'sm:col-span-2 aspect-[16/9]' : 'aspect-[16/10]'}`} />
            ))}
          </div>
        ) : null}
        {item.result_metric && item.description ? <p className="rich-copy max-w-3xl leading-8">{item.description}</p> : null}
        <Block title="چالش" text={item.challenge} />
        <Block title="راه‌حل" text={item.solution} />
        {tags.length ? (
          <section className="space-y-3">
            <h2 className="text-xl font-extrabold">خدمات انجام‌شده</h2>
            <ul className="flex flex-wrap gap-2">
              {tags.map((tag) => (
                <li key={tag} className="rounded-full border px-3 py-1 text-sm">{tag}</li>
              ))}
            </ul>
          </section>
        ) : null}
        <Block title="نتایج" text={item.results} />
        <Block title="مطالعه موردی" text={item.case_study} />
        <section className="rounded-3xl bg-[var(--saffron-soft,#f5f8ff)] p-8 text-center">
          <h2 className="text-2xl font-extrabold">پروژه مشابه می‌خواهید؟</h2>
          <div className="mt-5 flex flex-wrap justify-center gap-3">
            <Link href="/proposal" className="btn-saffron">درخواست پروپوزال</Link>
            <Link href="/consultation" className="btn-ghost">رزرو مشاوره</Link>
          </div>
        </section>
      </div>
    </SitePage>
  );
}
