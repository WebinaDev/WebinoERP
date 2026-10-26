import { articleLd, breadcrumbLd, itemMetadata, JsonLd, siteName, siteOrigin } from '@/lib/seo';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { apiServer, nullIfNotFound } from '@/lib/public-api-server';
import { FormWidget } from '@/builder/render/form-widget';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type Related = { slug: string; title: string; excerpt?: string | null };
type BlogPost = { title: string; slug?: string; cover_url?: string | null; updated_at?: string | null; body?: string | null; excerpt?: string | null; read_minutes?: number; published_at?: string | null; category?: { name: string } | null; related?: Related[] };

const fa = (n: number) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);

async function load(slug: string): Promise<BlogPost | null> {
  try {
    return (await apiServer<{ data: BlogPost }>(`/v1/public/blog/${encodeURIComponent(slug)}`)).data;
  } catch (err) {
    return nullIfNotFound(err);
  }
}

/** Adds ids to h2/h3 and returns the TOC; splits the body near its middle heading for the mid-article CTA. */
function prepare(html: string) {
  const toc: { id: string; text: string; level: number }[] = [];
  let n = 0;
  const withIds = html.replace(/<h([23])([^>]*)>([\s\S]*?)<\/h\1>/gi, (_m, lvl: string, attrs: string, inner: string) => {
    const id = `s-${++n}`;
    toc.push({ id, text: inner.replace(/<[^>]+>/g, '').trim(), level: Number(lvl) });
    return `<h${lvl}${attrs.replace(/\sid="[^"]*"/i, '')} id="${id}">${inner}</h${lvl}>`;
  });
  const mid = toc.length >= 2 ? withIds.indexOf(`id="${toc[Math.floor(toc.length / 2)].id}"`) : -1;
  const cut = mid > 0 ? withIds.lastIndexOf('<h', mid) : -1;
  return { toc, first: cut > 0 ? withIds.slice(0, cut) : withIds, second: cut > 0 ? withIds.slice(cut) : '' };
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return itemMetadata<Record<string, unknown>>(`/v1/public/blog/${encodeURIComponent(slug)}`, `/blog/${slug}`, (d) => ({ title: d.title as string, description: (d.excerpt || d.body) as string, image: d.cover_url as string, type: 'article' as const, publishedTime: d.published_at as string }));
}

export default async function BlogPostPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const post = await load(slug);
  if (!post) notFound();
  const [origin, publisher] = await Promise.all([siteOrigin(), siteName()]);
  const { toc, first, second } = prepare(post.body ?? '');
  const meta = [post.category?.name, post.published_at ? new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'long' }).format(new Date(post.published_at)) : '', post.read_minutes ? `${fa(post.read_minutes)} دقیقه مطالعه` : ''].filter(Boolean).join(' · ');
  const cta = (
    <aside className="my-10 rounded-3xl bg-[var(--wb-primary,#0054ff)]/5 p-6 text-center">
      <p className="mb-4 font-bold">می‌خواهید این را برای کسب‌وکار خودتان پیاده کنیم؟</p>
      <div className="flex flex-wrap justify-center gap-3">
        <Link href="/consultation" className="rounded-full bg-[var(--wb-primary,#0054ff)] px-5 py-2 text-sm font-bold text-white">دریافت مشاوره رایگان</Link>
        <Link href="/proposal" className="rounded-full border px-5 py-2 text-sm font-bold">درخواست پروپوزال</Link>
      </div>
    </aside>
  );
  return (
    <SitePage kicker="بلاگ" title={post.title} lead={post.excerpt || undefined}>
      <JsonLd data={[
        articleLd(origin, { title: post.title, path: `/blog/${slug}`, description: post.excerpt || post.body, image: post.cover_url, published: post.published_at, modified: post.updated_at, publisher }),
        breadcrumbLd(origin, [{ name: 'خانه', path: '/' }, { name: 'بلاگ', path: '/blog' }, ...(post.category ? [{ name: post.category.name, path: '/blog' }] : []), { name: post.title, path: `/blog/${slug}` }]),
      ]} />
      {meta ? <p className="mb-6 text-sm opacity-70">{meta}</p> : null}
      {toc.length >= 3 ? (
        <nav aria-label="فهرست مطالب" className="mb-8 rounded-2xl border p-5">
          <p className="mb-2 font-bold">فهرست مطالب</p>
          <ol className="space-y-1 text-sm">
            {toc.map((h) => <li key={h.id} className={h.level === 3 ? 'ps-4' : ''}><a href={`#${h.id}`}>{h.text}</a></li>)}
          </ol>
        </nav>
      ) : null}
      <div className="rich-copy" dangerouslySetInnerHTML={{ __html: first }} />
      {cta}
      {second ? <div className="rich-copy" dangerouslySetInnerHTML={{ __html: second }} /> : null}
      {post.related?.length ? (
        <section className="mt-12">
          <h2 className="mb-4 text-xl font-extrabold">مطالب مرتبط</h2>
          <ul className="grid gap-4 sm:grid-cols-3">
            {post.related.map((r) => (
              <li key={r.slug} className="rounded-2xl border p-4"><Link href={`/blog/${r.slug}`} className="font-bold">{r.title}</Link>{r.excerpt ? <p className="mt-1 text-sm opacity-70">{r.excerpt}</p> : null}</li>
            ))}
          </ul>
        </section>
      ) : null}
      <section className="mt-12">
        <FormWidget widget={{ id: 'post-newsletter', type: 'form', props: { formKey: 'newsletter', title: 'عضویت در خبرنامه', intro: 'تازه‌ترین مطالب وبینا را در ایمیل خود دریافت کنید.' } }} />
      </section>
    </SitePage>
  );
}
