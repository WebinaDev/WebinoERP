import { itemMetadata } from '@/lib/seo';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import type { ReactNode } from 'react';
import { Briefcase, Building2, CheckCircle2, ExternalLink, Globe, Layers, Lightbulb, Tag, Target, TrendingUp, Trophy } from 'lucide-react';
import { apiServer } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';
import { PortfolioGallery } from './PortfolioGallery';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

export const revalidate = 60;

/** Fallback single portfolio item template (used only when no builder page «portfolio-{slug}» is published) (data from داشبورد ← بازاریابی ← نمونه‌کار; no content in code).
 * Desktop: gallery + story on the main column, sticky info table on the side. Mobile stacks. */
type PortfolioItem = {
  title: string;
  description?: string | null;
  client?: string | null;
  result_metric?: string | null;
  cover_url?: string | null;
  images?: string[] | null;
  technologies?: string[] | null;
  challenge?: string | null;
  solution?: string | null;
  results?: string | null;
  case_study?: string | null;
  category?: { name: string } | null;
  industry?: { name?: string; title?: string } | null;
  service?: { name?: string; title?: string } | null;
  seo?: { live_url?: string; tags?: string[] } | null;
};

const paras = (t?: string | null) => (t ?? '').split('\n').map((s) => s.trim()).filter(Boolean);

function Story({ icon, title, text, tone }: { icon: ReactNode; title: string; text?: string | null; tone: string }) {
  const lines = paras(text);
  if (!lines.length) return null;
  return (
    <section className="rounded-3xl border border-[#e6e9f2] bg-white p-6 shadow-sm sm:p-8">
      <div className="mb-4 flex items-center gap-3">
        <span className={`grid h-11 w-11 place-items-center rounded-2xl ${tone}`}>{icon}</span>
        <h2 className="text-xl font-extrabold text-[#22242a]">{title}</h2>
      </div>
      <div className="space-y-3">
        {lines.map((line, i) => <p key={i} className="rich-copy leading-8 text-[#3a3d45]">{line}</p>)}
      </div>
    </section>
  );
}

function Row({ icon, label, children }: { icon: ReactNode; label: string; children: ReactNode }) {
  return (
    <tr className="border-b border-[#eef0f5] last:border-0">
      <th scope="row" className="w-[42%] py-3 pl-3 text-right align-top font-medium text-[#6b7080]">
        <span className="flex items-center gap-2"><span className="text-[#0054ff]">{icon}</span>{label}</span>
      </th>
      <td className="py-3 align-top font-semibold text-[#22242a]">{children}</td>
    </tr>
  );
}

const Chips = ({ items }: { items: string[] }) => (
  <ul className="flex flex-wrap gap-1.5">
    {items.map((t) => <li key={t} className="rounded-full bg-[#f0f4ff] px-2.5 py-0.5 text-xs font-medium text-[#0054ff]">{t}</li>)}
  </ul>
);

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return itemMetadata<Record<string, unknown>>(`/v1/public/portfolio/${encodeURIComponent(slug)}`, `/portfolio/${slug}`, (d) => ({ title: d.title as string, description: (d.excerpt || d.challenge || d.result_metric) as string, image: d.cover_url as string }));
}

export default async function PortfolioDetailPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  // Page-builder first: a published builder page «portfolio-{slug}» (صفحه‌ساز) owns layout and content.
  const built = await loadPublishedPage(`portfolio-${slug}`);
  if (built) return <StorefrontDocument document={built} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(built) }} />;
  let item: PortfolioItem | null = null;
  try {
    item = (await apiServer<{ data: PortfolioItem }>(`/v1/public/portfolio/${encodeURIComponent(slug)}`)).data;
  } catch {
    item = null;
  }
  if (!item) notFound();

  const gallery = Array.from(new Set([item.cover_url, ...(item.images ?? [])].filter((s): s is string => !!s)));
  const services = item.technologies ?? [];
  const tags = item.seo?.tags ?? [];
  const liveUrl = item.seo?.live_url || null;
  const host = liveUrl ? liveUrl.replace(/^https?:\/\//, '').replace(/\/$/, '') : null;
  const clientName = item.client ? item.client.replace(/\s*\([^)]*\)\s*$/, '') : null;
  const industry = item.industry?.name || item.industry?.title || null;
  const service = item.service?.name || item.service?.title || null;
  // case_study is legacy: it often concatenates challenge + solution + results. Show it only when the split fields are empty.
  const hasSplit = !!(item.challenge || item.solution || item.results);
  const ic = 'h-[18px] w-[18px]';

  return (
    <SitePage kicker={item.category?.name || 'نمونه‌کار'} title={item.title} lead={item.description || item.result_metric || undefined}>
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div className="min-w-0 space-y-8">
          <PortfolioGallery images={gallery} title={item.title} />
          {item.result_metric ? (
            <div className="flex items-start gap-3 rounded-2xl bg-[#0054ff] p-5 text-white">
              <TrendingUp className="mt-0.5 h-6 w-6 shrink-0" />
              <div>
                <p className="text-sm opacity-80">نتیجه کلیدی</p>
                <p className="text-lg font-extrabold">{item.result_metric}</p>
              </div>
            </div>
          ) : null}
          <div className="grid gap-6">
            <Story icon={<Target className="h-5 w-5" />} tone="bg-[#fff1ec] text-[#e5532d]" title="چالش" text={item.challenge} />
            <Story icon={<Lightbulb className="h-5 w-5" />} tone="bg-[#f0f4ff] text-[#0054ff]" title="راه‌حل ما" text={item.solution} />
            <Story icon={<Trophy className="h-5 w-5" />} tone="bg-[#eafaf1] text-[#16a34a]" title="نتایج" text={item.results} />
            {!hasSplit ? <Story icon={<Briefcase className="h-5 w-5" />} tone="bg-[#f0f4ff] text-[#0054ff]" title="مطالعه موردی" text={item.case_study} /> : null}
          </div>
          {services.length ? (
            <section className="rounded-3xl bg-[#f5f8ff] p-6 sm:p-8">
              <h2 className="mb-4 text-xl font-extrabold text-[#22242a]">کارهایی که انجام دادیم</h2>
              <ul className="grid gap-3 sm:grid-cols-2">
                {services.map((s) => (
                  <li key={s} className="flex items-center gap-2 rounded-xl bg-white px-4 py-3 font-medium text-[#22242a] shadow-sm">
                    <CheckCircle2 className="h-5 w-5 shrink-0 text-[#0054ff]" />{s}
                  </li>
                ))}
              </ul>
            </section>
          ) : null}
        </div>

        <aside className="space-y-5 lg:sticky lg:top-24">
          <div className="rounded-3xl border border-[#e6e9f2] bg-white p-6 shadow-sm">
            <h2 className="mb-3 text-lg font-extrabold text-[#22242a]">اطلاعات پروژه</h2>
            <table className="w-full text-sm">
              <tbody>
                {clientName ? <Row icon={<Building2 className={ic} />} label="کارفرما">{clientName}</Row> : null}
                {item.category?.name ? <Row icon={<Layers className={ic} />} label="دسته">{item.category.name}</Row> : null}
                {industry ? <Row icon={<Briefcase className={ic} />} label="صنعت">{industry}</Row> : null}
                {service ? <Row icon={<Layers className={ic} />} label="خدمت">{service}</Row> : null}
                {liveUrl ? (
                  <Row icon={<Globe className={ic} />} label="وب‌سایت">
                    <a href={liveUrl} target="_blank" rel="noopener" dir="ltr" className="inline-flex items-center gap-1 text-[#0054ff] hover:underline">{host}<ExternalLink className="h-3.5 w-3.5" /></a>
                  </Row>
                ) : null}
                {item.result_metric ? <Row icon={<TrendingUp className={ic} />} label="نتیجه">{item.result_metric}</Row> : null}
                {services.length ? <Row icon={<CheckCircle2 className={ic} />} label="خدمات"><Chips items={services} /></Row> : null}
                {tags.length ? <Row icon={<Tag className={ic} />} label="برچسب‌ها"><Chips items={tags} /></Row> : null}
              </tbody>
            </table>
            {liveUrl ? (
              <a href={liveUrl} target="_blank" rel="noopener" className="btn-saffron mt-5 flex w-full items-center justify-center gap-2">
                مشاهده سایت زنده<ExternalLink className="h-4 w-4" />
              </a>
            ) : null}
          </div>
          <div className="rounded-3xl bg-[#22242a] p-6 text-white">
            <h2 className="text-lg font-extrabold">پروژه مشابه می‌خواهید؟</h2>
            <p className="mt-2 text-sm leading-7 opacity-80">نیازتان را بگویید؛ پیشنهاد فنی و زمان‌بندی را برایتان می‌فرستیم.</p>
            <div className="mt-4 grid gap-2">
              <Link href="/proposal" className="btn-saffron text-center">درخواست پروپوزال</Link>
              <Link href="/consultation" className="rounded-full border border-white/30 px-4 py-2 text-center text-sm font-bold hover:bg-white/10">رزرو مشاوره</Link>
            </div>
          </div>
          <Link href="/portfolio" className="block text-center text-sm font-bold text-[#0054ff] hover:underline">همه نمونه‌کارها</Link>
        </aside>
      </div>
    </SitePage>
  );
}
