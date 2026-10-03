import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type AcademyCourse = {
  title: string;
  description?: string | null;
  lessons?: { title: string; slug: string; content?: string | null }[];
};

export default async function AcademyCoursePage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();
  const { slug } = await params;
  let course: AcademyCourse | null = null;
  try {
    const res = await apiServer<{ data: AcademyCourse }>(`/v1/public/academy/${slug}`);
    course = res.data;
  } catch {
    course = null;
  }
  if (!course) return <SitePage title={t('auto.academy__slug__page.s_d45e5bd4')} />;

  return (
    <SitePage kicker={t('site.nav.academy')} title={course.title} lead={course.description || undefined}>
      <ol className="space-y-3">
        {course.lessons?.map((lesson, i) => (
          <li key={lesson.slug} className="panel">
            <p className="text-xs font-bold text-[var(--saffron-deep)]">{String(i + 1).padStart(2, '0')}</p>
            <h2 className="mt-1 font-bold">{lesson.title}</h2>
            {lesson.content ? <div className="rich-copy mt-2 text-sm" dangerouslySetInnerHTML={{ __html: lesson.content }} /> : null}
          </li>
        ))}
      </ol>
    </SitePage>
  );
}
