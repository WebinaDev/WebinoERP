import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { itemMetadata, type Seo } from '@/lib/seo';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';

export const revalidate = 60;

/** Builder-only course route: page body lives solely in صفحه‌ساز page «academy-{slug}»; course data (syllabus, quizzes) comes from داشبورد ← بازاریابی ← آکادمی via academy-course-* widgets. */
const pageSlug = (slug: string) => `academy-${slug}`;

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  return itemMetadata<{ title?: string; seo?: Seo }>(
    `/v1/public/builder/pages/${encodeURIComponent(pageSlug(slug))}`,
    `/academy/${slug}`,
    (d) => ({ title: d.title, seo: d.seo }),
  );
}

export default async function BuilderAcademyCoursePage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const doc = await loadPublishedPage(pageSlug(slug));
  if (!doc) notFound();
  return <StorefrontDocument document={doc} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(doc) }} />;
}
