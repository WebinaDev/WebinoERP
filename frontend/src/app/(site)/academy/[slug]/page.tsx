import { breadcrumbLd, itemMetadata, JsonLd, siteName, siteOrigin } from '@/lib/seo';
import type { ReactNode } from 'react';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { apiServer } from '@/lib/public-api-server';

export const revalidate = 60;

type Quiz = { questions?: number; pass_score?: number; minutes?: number; attempts?: number; sample?: { q: string; options: string[]; answer: number }[] };
type Lesson = { title: string; slug: string; summary?: string | null; content?: string | null; chapter_id?: number | null; duration_minutes?: number | null; kind?: string | null; is_preview?: boolean; quiz?: Quiz | null };
type Lms = {
  tagline?: string; students_count?: number; rating?: number; reviews_count?: number; updated_label?: string; language?: string; access?: string; certificate?: string; total_minutes?: number;
  instructor?: { name: string; title?: string; bio?: string; years?: number; courses?: number; students?: number; skills?: string[] };
  audience?: string[]; prerequisites?: string[]; projects?: string[];
  materials?: { title: string; type?: string; size?: string }[];
  final_exam?: { title: string; questions?: number; pass_score?: number; minutes?: number; attempts?: number };
  faq?: { q: string; a: string }[];
  reviews?: { name: string; role?: string; rating?: number; text: string }[];
};
type Course = {
  title: string; description?: string | null; cover_url?: string | null; level?: string | null; duration?: string | null; instructor?: string | null;
  outcomes?: string | null; enroll_url?: string | null; is_free?: boolean; lms?: Lms | null;
  lessons?: Lesson[]; chapters?: { id: number; title: string; summary?: string | null }[];
};

const fa = (n: number | string) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[+d]);
const faNum = (n: number) => fa(n.toLocaleString('en-US').replace(/,/g, '٬'));
const mins = (m: number) => (m >= 60 ? `${fa(Math.floor(m / 60))} ساعت${m % 60 ? ` و ${fa(m % 60)} دقیقه` : ''}` : `${fa(m)} دقیقه`);
const KIND: Record<string, { label: string; icon: string }> = {
  video: { label: 'ویدیو', icon: '▶' }, reading: { label: 'مطالعه', icon: '📄' }, quiz: { label: 'آزمونک', icon: '✎' },
  exam: { label: 'آزمون جامع', icon: '🏁' }, project: { label: 'پروژه عملی', icon: '🛠' }, live: { label: 'جلسه زنده', icon: '●' },
};

async function load(slug: string): Promise<Course | null> {
  try {
    return (await apiServer<{ data: Course }>(`/v1/public/academy/${encodeURIComponent(slug)}`)).data;
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return itemMetadata<Record<string, unknown>>(`/v1/public/academy/${encodeURIComponent(slug)}`, `/academy/${slug}`, (d) => ({ title: d.title as string, description: (d.description as string | null)?.split('\n')[0], image: d.cover_url as string }));
}

function Stars({ value }: { value: number }) {
  return <span aria-label={`امتیاز ${fa(value)} از ۵`} className="tracking-tight text-amber-500">{'★★★★★'.slice(0, Math.round(value))}<span className="opacity-30">{'★★★★★'.slice(Math.round(value))}</span></span>;
}

function Section({ id, title, children }: { id: string; title: string; children: ReactNode }) {
  return (
    <section id={id} className="scroll-mt-28 rounded-3xl border border-black/5 bg-white/70 p-5 shadow-sm sm:p-7 dark:border-white/10 dark:bg-white/5">
      <h2 className="mb-4 text-xl font-extrabold">{title}</h2>
      {children}
    </section>
  );
}

export default async function AcademyCoursePage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const course = await load(slug);
  if (!course) notFound();
  const [origin, provider] = await Promise.all([siteOrigin(), siteName()]);
  const lms: Lms = course.lms ?? {};
  const outcomes = (course.outcomes ?? '').split('\n').map((s) => s.trim()).filter(Boolean);
  const [lead, ...rest] = (course.description ?? '').split('\n').map((s) => s.trim()).filter(Boolean);
  const enroll = course.enroll_url || '/consultation';
  const lessons = course.lessons ?? [];
  const chapters = course.chapters ?? [];
  const groups = chapters.length
    ? [...chapters.map((c) => ({ title: c.title, summary: c.summary, lessons: lessons.filter((l) => l.chapter_id === c.id) })), { title: '', summary: null, lessons: lessons.filter((l) => !chapters.some((c) => c.id === l.chapter_id)) }].filter((g) => g.lessons.length)
    : [{ title: '', summary: null as string | null | undefined, lessons }];
  const total = lms.total_minutes ?? lessons.reduce((s, l) => s + (l.duration_minutes ?? 0), 0);
  const quizzes = lessons.filter((l) => l.kind === 'quiz' || l.kind === 'exam');
  const projects = lessons.filter((l) => l.kind === 'project').length;
  const videos = lessons.filter((l) => !l.kind || l.kind === 'video').length;
  const ins = lms.instructor;
  const stats = [
    lms.students_count ? ['دانشجو', faNum(lms.students_count)] : null,
    lms.rating ? ['امتیاز', `${fa(lms.rating)} از ۵`] : null,
    total ? ['مدت دوره', mins(total)] : course.duration ? ['مدت دوره', course.duration] : null,
    lessons.length ? ['درس', fa(lessons.length)] : null,
    quizzes.length ? ['آزمون', fa(quizzes.length)] : null,
    course.level ? ['سطح', course.level] : null,
  ].filter(Boolean) as [string, string][];
  const includes = [
    total ? `${mins(total)} محتوای آموزشی` : null,
    videos ? `${fa(videos)} ویدیوی آموزشی` : null,
    quizzes.length ? `${fa(quizzes.length)} آزمونک و آزمون جامع` : null,
    projects ? `${fa(projects)} پروژه عملی` : null,
    lms.materials?.length ? `${fa(lms.materials.length)} فایل و قالب قابل دانلود` : null,
    lms.access, lms.certificate,
  ].filter(Boolean) as string[];
  const nav = [['about', 'معرفی'], ['syllabus', 'سرفصل‌ها'], ['instructor', 'مدرس'], ['exams', 'آزمون‌ها'], ['materials', 'منابع'], ['reviews', 'نظرات'], ['faq', 'پرسش‌ها']].filter(([id]) =>
    id === 'instructor' ? !!ins : id === 'exams' ? quizzes.length > 0 : id === 'materials' ? !!lms.materials?.length : id === 'reviews' ? !!lms.reviews?.length : id === 'faq' ? !!lms.faq?.length : true);

  return (
    <div dir="rtl">
      <JsonLd data={[
        { '@context': 'https://schema.org', '@type': 'Course', name: course.title, description: (course.description ?? course.title).slice(0, 500), inLanguage: 'fa-IR',
          provider: { '@type': 'Organization', name: provider, sameAs: origin }, url: `${origin}/academy/${slug}`, ...(course.cover_url ? { image: `${origin}${course.cover_url}` } : {}),
          ...(course.level ? { educationalLevel: course.level } : {}), ...(course.is_free ? { isAccessibleForFree: true } : {}),
          ...(lms.rating && lms.reviews_count ? { aggregateRating: { '@type': 'AggregateRating', ratingValue: lms.rating, reviewCount: lms.reviews_count, bestRating: 5 } } : {}),
          ...(outcomes.length ? { teaches: outcomes } : {}),
          hasCourseInstance: { '@type': 'CourseInstance', courseMode: 'online', ...(ins ? { instructor: { '@type': 'Person', name: ins.name, description: ins.title } } : {}), ...(total ? { courseWorkload: `PT${Math.floor(total / 60)}H${total % 60}M` } : {}) } },
        ...(lms.faq?.length ? [{ '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity: lms.faq.map((f) => ({ '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } })) }] : []),
        breadcrumbLd(origin, [{ name: 'خانه', path: '/' }, { name: 'آکادمی', path: '/academy' }, { name: course.title, path: `/academy/${slug}` }]),
      ]} />

      <header className="page-hero !pb-10">
        <div className="webina-shell">
          <nav className="mb-4 text-sm opacity-70"><Link href="/academy">آکادمی</Link> <span aria-hidden>‹</span> <span>{course.title}</span></nav>
          <div className="grid items-center gap-8 lg:grid-cols-[1.25fr_1fr]">
            <div className="min-w-0">
              <p className="webina-kicker">دوره آنلاین آکادمی وبینا</p>
              <h1 className="mt-4">{course.title}</h1>
              {lms.tagline ? <p className="mt-3 text-lg font-bold text-[var(--saffron-deep)]">{lms.tagline}</p> : null}
              {lead ? <p className="lead">{lead}</p> : null}
              <div className="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                {lms.rating ? <span className="flex items-center gap-1"><b>{fa(lms.rating)}</b> <Stars value={lms.rating} /> {lms.reviews_count ? <span className="opacity-70">({faNum(lms.reviews_count)} نظر)</span> : null}</span> : null}
                {lms.students_count ? <span>👥 {faNum(lms.students_count)} دانشجو</span> : null}
                {ins ? <span>مدرس: <a href="#instructor" className="font-bold underline-offset-4 hover:underline">{ins.name}</a></span> : null}
                {lms.updated_label ? <span className="opacity-70">به‌روزرسانی: {lms.updated_label}</span> : null}
              </div>
              <div className="mt-6 flex flex-wrap gap-3">
                <Link href={enroll} className="rounded-full bg-[var(--wb-primary,#0054ff)] px-6 py-3 font-bold text-white shadow-lg">{course.is_free ? 'شروع رایگان دوره' : 'ثبت‌نام در دوره'}</Link>
                <a href="#syllabus" className="rounded-full border border-current px-6 py-3 font-bold">مشاهده سرفصل‌ها</a>
              </div>
            </div>
            {course.cover_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={course.cover_url} alt={course.title} className="aspect-[16/10] w-full rounded-3xl object-cover shadow-2xl" />
            ) : null}
          </div>
          {stats.length ? (
            <dl className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
              {stats.map(([k, v]) => (
                <div key={k} className="rounded-2xl bg-white/80 px-4 py-3 text-center shadow-sm dark:bg-white/10">
                  <dd className="text-lg font-extrabold">{v}</dd>
                  <dt className="text-xs opacity-60">{k}</dt>
                </div>
              ))}
            </dl>
          ) : null}
        </div>
      </header>

      <div className="webina-shell page-body">
        <nav className="sticky top-16 z-10 -mx-1 mb-6 flex gap-2 overflow-x-auto rounded-2xl bg-white/90 p-2 text-sm font-bold shadow-sm backdrop-blur dark:bg-black/60">
          {nav.map(([id, label]) => <a key={id} href={`#${id}`} className="shrink-0 rounded-xl px-3 py-2 hover:bg-black/5 dark:hover:bg-white/10">{label}</a>)}
        </nav>

        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
          <div className="min-w-0 space-y-6">
            <Section id="about" title="درباره این دوره">
              {rest.map((p) => <p key={p} className="mb-3 leading-8">{p}</p>)}
              {outcomes.length ? (
                <>
                  <h3 className="mb-3 mt-5 text-lg font-bold">بعد از این دوره می‌توانید</h3>
                  <ul className="grid gap-2 sm:grid-cols-2">{outcomes.map((o) => <li key={o} className="flex gap-2"><span className="text-emerald-600">✔</span><span>{o}</span></li>)}</ul>
                </>
              ) : null}
              <div className="mt-6 grid gap-6 sm:grid-cols-2">
                {lms.audience?.length ? <div><h3 className="mb-2 font-bold">این دوره برای چه کسانی است؟</h3><ul className="list-disc space-y-1 ps-5 text-sm leading-7">{lms.audience.map((a) => <li key={a}>{a}</li>)}</ul></div> : null}
                {lms.prerequisites?.length ? <div><h3 className="mb-2 font-bold">پیش‌نیازها</h3><ul className="list-disc space-y-1 ps-5 text-sm leading-7">{lms.prerequisites.map((a) => <li key={a}>{a}</li>)}</ul></div> : null}
              </div>
            </Section>

            {lessons.length ? (
              <Section id="syllabus" title="سرفصل‌های دوره">
                <p className="mb-4 text-sm opacity-70">{fa(groups.length)} فصل · {fa(lessons.length)} درس · {mins(total)}</p>
                <div className="space-y-3">
                  {groups.map((g, gi) => {
                    const gm = g.lessons.reduce((s, l) => s + (l.duration_minutes ?? 0), 0);
                    return (
                      <details key={gi} open={gi === 0} className="group overflow-hidden rounded-2xl border border-black/10 dark:border-white/10">
                        <summary className="flex cursor-pointer list-none items-center gap-3 bg-black/[.03] px-4 py-3 dark:bg-white/5">
                          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--wb-primary,#0054ff)] text-sm font-extrabold text-white">{fa(gi + 1)}</span>
                          <span className="min-w-0 flex-1">
                            <span className="block font-bold">{g.title ? `فصل ${fa(gi + 1)}: ${g.title}` : 'درس‌ها'}</span>
                            <span className="block text-xs opacity-60">{fa(g.lessons.length)} درس{gm ? ` · ${mins(gm)}` : ''}</span>
                          </span>
                          <span className="transition group-open:rotate-180" aria-hidden>⌄</span>
                        </summary>
                        {g.summary ? <p className="px-4 pt-3 text-sm opacity-75">{g.summary}</p> : null}
                        <ol className="divide-y divide-black/5 dark:divide-white/10">
                          {g.lessons.map((l, i) => {
                            const k = KIND[l.kind ?? 'video'] ?? KIND.video;
                            return (
                              <li key={l.slug} className="flex items-start gap-3 px-4 py-3">
                                <span className="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-black/5 text-xs dark:bg-white/10" title={k.label}>{k.icon}</span>
                                <div className="min-w-0 flex-1">
                                  <p className="font-bold"><span className="me-1 text-xs text-[var(--saffron-deep)]">{fa(gi + 1)}.{fa(i + 1)}</span> {l.title}</p>
                                  {l.summary ? <p className="mt-0.5 text-sm opacity-70">{l.summary}</p> : null}
                                  {l.quiz?.questions ? <p className="mt-1 text-xs opacity-70">{fa(l.quiz.questions)} سؤال · حداقل نمره قبولی {fa(l.quiz.pass_score ?? 60)}٪{l.quiz.attempts ? ` · ${fa(l.quiz.attempts)} بار فرصت` : ''}</p> : null}
                                </div>
                                <div className="shrink-0 text-end text-xs">
                                  {l.is_preview ? <span className="mb-1 block rounded-full bg-emerald-100 px-2 py-0.5 font-bold text-emerald-700">پیش‌نمایش رایگان</span> : <span className="mb-1 block opacity-50">🔒 {k.label}</span>}
                                  {l.duration_minutes ? <span className="opacity-70">{mins(l.duration_minutes)}</span> : null}
                                </div>
                              </li>
                            );
                          })}
                        </ol>
                      </details>
                    );
                  })}
                </div>
              </Section>
            ) : null}

            {ins ? (
              <Section id="instructor" title="مدرس دوره">
                <div className="flex flex-col gap-5 sm:flex-row">
                  <div className="grid h-24 w-24 shrink-0 place-items-center rounded-3xl bg-gradient-to-br from-[var(--wb-primary,#0054ff)] to-[var(--saffron-deep,#c77d00)] text-3xl font-extrabold text-white">{ins.name.trim().charAt(0)}</div>
                  <div className="min-w-0">
                    <h3 className="text-lg font-extrabold">{ins.name}</h3>
                    {ins.title ? <p className="text-sm text-[var(--saffron-deep)]">{ins.title}</p> : null}
                    <div className="mt-2 flex flex-wrap gap-4 text-sm opacity-80">
                      {ins.years ? <span>{fa(ins.years)} سال تجربه</span> : null}
                      {ins.students ? <span>{faNum(ins.students)} دانشجو</span> : null}
                      {ins.courses ? <span>{fa(ins.courses)} دوره</span> : null}
                    </div>
                    {ins.bio ? <p className="mt-3 leading-8">{ins.bio}</p> : null}
                    {ins.skills?.length ? <div className="mt-3 flex flex-wrap gap-2">{ins.skills.map((s) => <span key={s} className="rounded-full bg-black/5 px-3 py-1 text-xs font-bold dark:bg-white/10">{s}</span>)}</div> : null}
                  </div>
                </div>
              </Section>
            ) : null}

            {quizzes.length ? (
              <Section id="exams" title="آزمون‌ها و ارزیابی">
                <p className="mb-4 text-sm opacity-75">بعد از هر فصل یک آزمونک کوتاه دارید و در پایان، آزمون جامع. گواهی دوره پس از دیدن همه درس‌ها و قبولی در آزمون جامع صادر می‌شود.</p>
                <div className="grid gap-3 sm:grid-cols-2">
                  {quizzes.map((q) => (
                    <div key={q.slug} className={`rounded-2xl border p-4 ${q.kind === 'exam' ? 'border-[var(--saffron-deep)] bg-amber-50/60 dark:bg-amber-500/10' : 'border-black/10 dark:border-white/10'}`}>
                      <p className="text-xs font-bold opacity-60">{KIND[q.kind ?? 'quiz']?.label}</p>
                      <h3 className="font-bold">{q.title}</h3>
                      <p className="mt-1 text-sm opacity-75">{fa(q.quiz?.questions ?? 0)} سؤال · {mins(q.quiz?.minutes ?? q.duration_minutes ?? 0)} · قبولی {fa(q.quiz?.pass_score ?? 60)}٪</p>
                      {q.quiz?.sample?.[0] ? (
                        <details className="mt-2 text-sm">
                          <summary className="cursor-pointer font-bold text-[var(--wb-primary,#0054ff)]">نمونه سؤال</summary>
                          <p className="mt-2">{q.quiz.sample[0].q}</p>
                          <ol className="mt-1 list-[persian] space-y-1 ps-5">{q.quiz.sample[0].options.map((o, oi) => <li key={o} className={oi === q.quiz!.sample![0].answer ? 'font-bold text-emerald-700' : ''}>{o}</li>)}</ol>
                        </details>
                      ) : null}
                    </div>
                  ))}
                </div>
                {lms.projects?.length ? (
                  <>
                    <h3 className="mb-2 mt-6 font-bold">پروژه‌های عملی</h3>
                    <ul className="space-y-2">{lms.projects.map((p) => <li key={p} className="flex gap-2"><span>🛠</span><span>{p}</span></li>)}</ul>
                  </>
                ) : null}
              </Section>
            ) : null}

            {lms.materials?.length ? (
              <Section id="materials" title="منابع و فایل‌های دوره">
                <ul className="grid gap-3 sm:grid-cols-2">
                  {lms.materials.map((m) => (
                    <li key={m.title} className="flex items-center gap-3 rounded-2xl border border-black/10 p-3 dark:border-white/10">
                      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-black/5 text-[10px] font-extrabold dark:bg-white/10" dir="ltr">{m.type}</span>
                      <span className="min-w-0 flex-1"><span className="block font-bold">{m.title}</span>{m.size ? <span className="text-xs opacity-60">{m.size}</span> : null}</span>
                      <span className="text-xs opacity-50">🔒</span>
                    </li>
                  ))}
                </ul>
                <p className="mt-3 text-xs opacity-60">فایل‌ها پس از ثبت‌نام در پنل دانشجو قابل دانلود هستند.</p>
              </Section>
            ) : null}

            {lms.reviews?.length ? (
              <Section id="reviews" title="نظر دانشجویان">
                <div className="grid gap-3 md:grid-cols-3">
                  {lms.reviews.map((r) => (
                    <figure key={r.name + r.text} className="rounded-2xl bg-black/[.03] p-4 dark:bg-white/5">
                      {r.rating ? <Stars value={r.rating} /> : null}
                      <blockquote className="mt-2 text-sm leading-7">«{r.text}»</blockquote>
                      <figcaption className="mt-3 text-xs"><b>{r.name}</b>{r.role ? <span className="opacity-60"> · {r.role}</span> : null}</figcaption>
                    </figure>
                  ))}
                </div>
              </Section>
            ) : null}

            {lms.faq?.length ? (
              <Section id="faq" title="پرسش‌های پرتکرار">
                <div className="space-y-2">{lms.faq.map((f) => (
                  <details key={f.q} className="rounded-2xl border border-black/10 px-4 py-3 dark:border-white/10">
                    <summary className="cursor-pointer font-bold">{f.q}</summary>
                    <p className="mt-2 text-sm leading-7 opacity-80">{f.a}</p>
                  </details>
                ))}</div>
              </Section>
            ) : null}
          </div>

          <aside className="lg:sticky lg:top-32 lg:self-start">
            <div className="rounded-3xl border border-black/5 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-neutral-900">
              <p className="text-2xl font-extrabold">{course.is_free ? 'رایگان' : 'ثبت‌نام و مشاوره'}</p>
              <div className="mt-4">
                <div className="mb-1 flex justify-between text-xs opacity-70"><span>پیشرفت شما</span><span>۰٪</span></div>
                <div className="h-2 overflow-hidden rounded-full bg-black/10 dark:bg-white/10"><div className="h-full w-0 bg-[var(--wb-primary,#0054ff)]" /></div>
                <p className="mt-1 text-xs opacity-60">برای ثبت پیشرفت و دریافت گواهی ثبت‌نام کنید.</p>
              </div>
              <Link href={enroll} className="mt-4 block rounded-full bg-[var(--wb-primary,#0054ff)] px-6 py-3 text-center font-bold text-white">{course.is_free ? 'شروع دوره' : 'ثبت‌نام در دوره'}</Link>
              <a href="#syllabus" className="mt-2 block rounded-full border border-black/15 px-6 py-2.5 text-center text-sm font-bold dark:border-white/20">درس‌های پیش‌نمایش رایگان</a>
              {includes.length ? (
                <>
                  <h3 className="mb-2 mt-5 text-sm font-extrabold">این دوره شامل</h3>
                  <ul className="space-y-2 text-sm">{includes.map((x) => <li key={x} className="flex gap-2"><span className="text-emerald-600">✔</span><span>{x}</span></li>)}</ul>
                </>
              ) : null}
              {lms.language ? <p className="mt-4 text-xs opacity-60">زبان: {lms.language}</p> : null}
            </div>
          </aside>
        </div>

        <div className="mt-10 rounded-3xl bg-[var(--wb-primary,#0054ff)] p-6 text-center text-white sm:p-10">
          <h2 className="text-2xl font-extrabold">آماده شروع هستید؟</h2>
          <p className="mx-auto mt-2 max-w-xl opacity-90">{lms.tagline ?? course.title}</p>
          <Link href={enroll} className="mt-5 inline-block rounded-full bg-white px-7 py-3 font-bold text-[var(--wb-primary,#0054ff)]">ثبت‌نام در دوره</Link>
        </div>
      </div>
    </div>
  );
}
