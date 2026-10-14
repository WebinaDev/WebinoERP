'use client';

/* «ساختار دوره»: AI writes the course structure first (description, chapters → lessons), then each chapter
 * and each lesson in order. Everything is an editable draft in this panel; only «ذخیره ساختار» writes
 * chapters/lessons (new lessons unpublished) and «اعمال توضیح» updates the course description. */

import { useEffect, useState } from 'react';
import { ArrowDown, ArrowUp, Plus, Sparkles, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { contentAssist } from '@/features/ai/content-assist';

type Lesson = { id?: number; title: string; summary?: string | null; content?: string | null; published?: boolean };
type Chapter = { id?: number; title: string; summary?: string | null; lessons: Lesson[] };
type Course = { id: number; title: string; description?: string | null; level?: string | null; duration?: string | null; outcomes?: string | null };

const errMsg = (e: unknown) => (e as { response?: { data?: { message?: string } } })?.response?.data?.message || 'انجام نشد.';

export function CourseStructurePanel() {
  const [courses, setCourses] = useState<Course[]>([]);
  const [courseId, setCourseId] = useState<number | null>(null);
  const [course, setCourse] = useState<Course | null>(null);
  const [chapters, setChapters] = useState<Chapter[]>([]);
  const [desc, setDesc] = useState<string | null>(null);
  const [outcomes, setOutcomes] = useState<string | null>(null);
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState<string>('');
  const [msg, setMsg] = useState('');
  const [dirty, setDirty] = useState(false);

  useEffect(() => {
    apiClient.get('/v1/marketing/academy').then((r) => setCourses(normalizeListPayload(r.data) as unknown as Course[])).catch(() => setCourses([]));
  }, []);

  async function load(id: number) {
    setCourseId(id);
    setDesc(null);
    setOutcomes(null);
    setMsg('');
    const r = await apiClient.get(`/v1/marketing/academy/${id}/structure`);
    const d = r.data?.data ?? r.data;
    setCourse(d.course);
    const grouped: Chapter[] = (d.chapters ?? []).map((c: Chapter) => ({ ...c, lessons: c.lessons ?? [] }));
    if (d.ungrouped_lessons?.length) grouped.push({ title: 'بدون فصل (برای گروه‌بندی جابه‌جا کنید)', lessons: d.ungrouped_lessons });
    setChapters(grouped);
    setDirty(false);
  }

  const outline = () => chapters.map((c, i) => `فصل ${i + 1}: ${c.title}\n${c.lessons.map((l, j) => `  ${i + 1}.${j + 1} ${l.title}`).join('\n')}`).join('\n');
  const baseCtx = () => ({ course_title: course?.title, course_description: desc ?? course?.description, level: course?.level, duration: course?.duration });
  const edit = (next: Chapter[]) => { setChapters(next); setDirty(true); };

  async function genStructure() {
    setBusy('structure');
    setMsg('');
    try {
      const r = await contentAssist({ action: 'course_structure', surface: 'academy.structure', target_id: courseId, text: notes, context: baseCtx() });
      const st = r.structured as { description?: string; outcomes?: string[]; chapters?: { title: string; summary?: string; lessons?: { title: string; summary?: string }[] }[] };
      if (st.description) setDesc(st.description);
      if (st.outcomes?.length) setOutcomes(st.outcomes.join('\n'));
      if (st.chapters?.length) {
        const keep = chapters.some((c) => c.id) ? chapters : [];
        edit([...keep, ...st.chapters.map((c) => ({ title: c.title, summary: c.summary ?? '', lessons: (c.lessons ?? []).map((l) => ({ title: l.title, summary: l.summary ?? '' })) }))]);
      }
      setMsg('ساختار پیشنهادی آماده است؛ ویرایش کنید، سپس فصل‌ها و درس‌ها را تولید کنید.');
    } catch (e) {
      setMsg(errMsg(e));
    } finally {
      setBusy('');
    }
  }

  async function genChapter(ci: number, list = chapters): Promise<Chapter[]> {
    const c = list[ci];
    setBusy(`c${ci}`);
    try {
      const r = await contentAssist({ action: 'chapter_content', surface: 'academy.chapter', target_id: courseId, text: c.title, context: { ...baseCtx(), structure: outline(), existing_lessons: c.lessons.map((l) => l.title).join('، ') } });
      const st = r.structured as { summary?: string; lessons?: { title: string; summary?: string }[] };
      const lessons = c.lessons.length ? c.lessons.map((l, i) => ({ ...l, summary: l.summary || st.lessons?.[i]?.summary || '' })) : (st.lessons ?? []).map((l) => ({ title: l.title, summary: l.summary ?? '' }));
      const next = list.map((x, i) => (i === ci ? { ...x, summary: st.summary || x.summary, lessons } : x));
      edit(next);
      return next;
    } finally {
      setBusy('');
    }
  }

  async function genLesson(ci: number, li: number, list = chapters): Promise<Chapter[]> {
    const c = list[ci];
    const l = c.lessons[li];
    setBusy(`l${ci}.${li}`);
    try {
      const r = await contentAssist({ action: 'lesson_content', surface: 'academy.lesson', target_id: l.id ?? courseId, text: l.title, format: 'html', context: { ...baseCtx(), chapter: c.title, lesson_summary: l.summary, previous_lesson: c.lessons[li - 1]?.title, next_lesson: c.lessons[li + 1]?.title, structure: outline() } });
      const next = list.map((x, i) => (i === ci ? { ...x, lessons: x.lessons.map((y, j) => (j === li ? { ...y, content: r.suggestion } : y)) } : x));
      edit(next);
      return next;
    } finally {
      setBusy('');
    }
  }

  /** Chapters first, then every lesson without content, strictly in order. */
  async function genAll() {
    setMsg('');
    try {
      let list = chapters;
      for (let ci = 0; ci < list.length; ci++) {
        if (!list[ci].summary || !list[ci].lessons.length) list = await genChapter(ci, list);
        for (let li = 0; li < list[ci].lessons.length; li++) if (!list[ci].lessons[li].content) list = await genLesson(ci, li, list);
      }
      setMsg('همه فصل‌ها و درس‌ها پیش‌نویس شدند؛ بازبینی و سپس ذخیره کنید.');
    } catch (e) {
      setMsg(errMsg(e));
    }
  }

  async function save() {
    if (!courseId) return;
    setBusy('save');
    try {
      const body = { chapters: chapters.filter((c) => c.title.trim()).map((c) => ({ id: c.id, title: c.title, summary: c.summary ?? '', lessons: c.lessons.filter((l) => l.title.trim()).map((l) => ({ id: l.id, title: l.title, summary: l.summary ?? '', content: l.content ?? '' })) })) };
      await apiClient.put(`/v1/marketing/academy/${courseId}/structure`, body);
      await load(courseId);
      setMsg('ساختار ذخیره شد. درس‌های جدید منتشرنشده‌اند؛ از «سرفصل‌های آکادمی» منتشرشان کنید.');
    } catch (e) {
      setMsg(errMsg(e));
    } finally {
      setBusy('');
    }
  }

  async function applyCourse(field: 'description' | 'outcomes', value: string) {
    if (!courseId) return;
    try {
      await apiClient.put(`/v1/marketing/academy/${courseId}`, { [field]: value });
      setCourse((c) => (c ? { ...c, [field]: value } : c));
      if (field === 'description') setDesc(null); else setOutcomes(null);
      setMsg('در دوره ذخیره شد.');
    } catch (e) {
      setMsg(errMsg(e));
    }
  }

  const move = <T,>(arr: T[], i: number, d: number) => { const j = i + d; if (j < 0 || j >= arr.length) return arr; const n = [...arr]; [n[i], n[j]] = [n[j], n[i]]; return n; };
  const input = 'border-input h-9 w-full rounded-md border bg-transparent px-2';

  return (
    <Card className="mt-6">
      <CardContent className="grid gap-4 pt-6 text-sm" dir="rtl">
        <div className="flex flex-wrap items-center gap-3">
          <h2 className="text-base font-bold">ساختار دوره (فصل‌ها و درس‌ها)</h2>
          <select className="border-input h-9 rounded-md border bg-transparent px-2" value={courseId ?? ''} onChange={(e) => e.target.value && void load(Number(e.target.value))}>
            <option value="">انتخاب دوره…</option>
            {courses.map((c) => <option key={c.id} value={c.id}>{c.title}</option>)}
          </select>
          {dirty ? <span className="text-xs text-amber-600">تغییرات ذخیره‌نشده</span> : null}
        </div>
        {course ? (
          <>
            <div className="grid gap-2 rounded-lg border p-3">
              <label className="grid gap-1">یادداشت برای AI (مخاطب، هدف، نکات؛ اختیاری)
                <textarea className="border-input min-h-16 rounded-md border bg-transparent px-2 py-1" value={notes} onChange={(e) => setNotes(e.target.value)} />
              </label>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" disabled={!!busy} onClick={() => void genStructure()}><Sparkles className="size-4" />۱. ساخت ساختار با AI</Button>
                <Button size="sm" variant="outline" disabled={!!busy || !chapters.length} onClick={() => void genAll()}><Sparkles className="size-4" />۲. تولید فصل‌ها و درس‌ها به ترتیب</Button>
                <Button size="sm" variant="outline" disabled={!!busy || !dirty} onClick={() => void save()}>ذخیره ساختار</Button>
              </div>
              {busy ? <p className="text-violet-700">در حال تولید… ({busy})</p> : null}
              {msg ? <p className="text-slate-600">{msg}</p> : null}
            </div>
            {desc !== null ? (
              <div className="grid gap-1 rounded-lg border border-violet-200 bg-violet-50/40 p-3">
                <span className="font-semibold">توضیح دوره پیشنهادی (قابل ویرایش)</span>
                <textarea className="border-input min-h-20 rounded-md border bg-white px-2 py-1" value={desc} onChange={(e) => setDesc(e.target.value)} />
                <div className="flex gap-2"><Button size="sm" onClick={() => void applyCourse('description', desc)}>اعمال توضیح</Button><Button size="sm" variant="outline" onClick={() => setDesc(null)}>رد</Button></div>
              </div>
            ) : null}
            {outcomes !== null ? (
              <div className="grid gap-1 rounded-lg border border-violet-200 bg-violet-50/40 p-3">
                <span className="font-semibold">خروجی‌های یادگیری پیشنهادی</span>
                <textarea className="border-input min-h-20 rounded-md border bg-white px-2 py-1" value={outcomes} onChange={(e) => setOutcomes(e.target.value)} />
                <div className="flex gap-2"><Button size="sm" onClick={() => void applyCourse('outcomes', outcomes)}>اعمال خروجی‌ها</Button><Button size="sm" variant="outline" onClick={() => setOutcomes(null)}>رد</Button></div>
              </div>
            ) : null}
            <ol className="grid gap-3">
              {chapters.map((c, ci) => (
                <li key={ci} className="grid gap-2 rounded-lg border p-3">
                  <div className="flex items-center gap-2">
                    <span className="font-bold">فصل {ci + 1}</span>
                    <input className={input} value={c.title} onChange={(e) => edit(chapters.map((x, i) => (i === ci ? { ...x, title: e.target.value } : x)))} />
                    <button type="button" title="تولید فصل" disabled={!!busy} onClick={() => void genChapter(ci).catch((e) => setMsg(errMsg(e)))}><Sparkles className="size-4 text-violet-600" /></button>
                    <button type="button" aria-label="بالا" onClick={() => edit(move(chapters, ci, -1))}><ArrowUp className="size-4" /></button>
                    <button type="button" aria-label="پایین" onClick={() => edit(move(chapters, ci, 1))}><ArrowDown className="size-4" /></button>
                    {!c.id ? <button type="button" aria-label="حذف" onClick={() => edit(chapters.filter((_, i) => i !== ci))}><Trash2 className="size-4 text-red-500" /></button> : null}
                  </div>
                  <textarea className="border-input min-h-12 rounded-md border bg-transparent px-2 py-1 text-xs" placeholder="خلاصه فصل" value={c.summary ?? ''} onChange={(e) => edit(chapters.map((x, i) => (i === ci ? { ...x, summary: e.target.value } : x)))} />
                  <ol className="grid gap-2 ps-4">
                    {c.lessons.map((l, li) => (
                      <li key={li} className="grid gap-1 rounded-md bg-slate-50 p-2">
                        <div className="flex items-center gap-2">
                          <span className="text-xs">{ci + 1}.{li + 1}</span>
                          <input className={input} value={l.title} onChange={(e) => edit(chapters.map((x, i) => (i === ci ? { ...x, lessons: x.lessons.map((y, j) => (j === li ? { ...y, title: e.target.value } : y)) } : x)))} />
                          <button type="button" title="تولید درس" disabled={!!busy} onClick={() => void genLesson(ci, li).catch((e) => setMsg(errMsg(e)))}><Sparkles className="size-4 text-violet-600" /></button>
                          {ci > 0 ? <button type="button" title="انتقال به فصل قبل" onClick={() => edit(chapters.map((x, i) => (i === ci ? { ...x, lessons: x.lessons.filter((_, j) => j !== li) } : i === ci - 1 ? { ...x, lessons: [...x.lessons, l] } : x)))}><ArrowUp className="size-4" /></button> : null}
                          {ci < chapters.length - 1 ? <button type="button" title="انتقال به فصل بعد" onClick={() => edit(chapters.map((x, i) => (i === ci ? { ...x, lessons: x.lessons.filter((_, j) => j !== li) } : i === ci + 1 ? { ...x, lessons: [l, ...x.lessons] } : x)))}><ArrowDown className="size-4" /></button> : null}
                          {!l.id ? <button type="button" aria-label="حذف" onClick={() => edit(chapters.map((x, i) => (i === ci ? { ...x, lessons: x.lessons.filter((_, j) => j !== li) } : x)))}><Trash2 className="size-4 text-red-500" /></button> : <span className="text-[10px] text-slate-500">{l.published ? 'منتشرشده' : 'پیش‌نویس'}</span>}
                        </div>
                        <input className={`${input} text-xs`} placeholder="خلاصه درس" value={l.summary ?? ''} onChange={(e) => edit(chapters.map((x, i) => (i === ci ? { ...x, lessons: x.lessons.map((y, j) => (j === li ? { ...y, summary: e.target.value } : y)) } : x)))} />
                        {l.content ? (
                          <details><summary className="cursor-pointer text-xs text-violet-700">متن درس (HTML، قابل ویرایش)</summary>
                            <textarea className="border-input mt-1 min-h-32 w-full rounded-md border bg-white px-2 py-1 text-xs" dir="auto" value={l.content} onChange={(e) => edit(chapters.map((x, i) => (i === ci ? { ...x, lessons: x.lessons.map((y, j) => (j === li ? { ...y, content: e.target.value } : y)) } : x)))} />
                          </details>
                        ) : null}
                      </li>
                    ))}
                    <li><button type="button" className="flex items-center gap-1 text-xs text-slate-600" onClick={() => edit(chapters.map((x, i) => (i === ci ? { ...x, lessons: [...x.lessons, { title: 'درس جدید' }] } : x)))}><Plus className="size-3" />درس</button></li>
                  </ol>
                </li>
              ))}
            </ol>
            <button type="button" className="flex items-center gap-1 text-xs text-slate-600" onClick={() => edit([...chapters, { title: 'فصل جدید', lessons: [] }])}><Plus className="size-3" />فصل</button>
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
