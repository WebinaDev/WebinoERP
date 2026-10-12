import { apiServer } from '@/lib/public-api-server';
import type { BuilderDocument, SectionNode, WidgetNode } from '@/builder/types';

function widgets(sections: SectionNode[]): WidgetNode[] {
  return sections.flatMap((s) => s.columns.flatMap((c) => c.widgets.flatMap((w) => [w, ...widgets(w.children ?? [])])));
}

/**
 * Server-side data for «وبینا» widgets (site settings, home feed, FAQ, form fields), keyed by
 * public API path. Widgets read it from the builder runtime, so pages render fully on the server.
 */
export async function prefetchCorporate(document: BuilderDocument): Promise<Record<string, unknown>> {
  const list = widgets(document.sections).filter((w) => w.type.startsWith('webina-'));
  const paths = new Set<string>(['/v1/public/site']);
  for (const w of list) {
    if (w.type.startsWith('webina-home-')) paths.add('/v1/public/home');
    if (w.type === 'webina-contact-faq') paths.add('/v1/public/faq');
    if (w.type === 'webina-contact-form') {
      const slug = typeof w.props.form_slug === 'string' && w.props.form_slug ? w.props.form_slug : 'contact';
      paths.add(`/v1/public/forms/${encodeURIComponent(slug)}`);
    }
  }
  const out: Record<string, unknown> = {};
  await Promise.all(
    [...paths].map(async (path) => {
      try {
        const res = await apiServer<{ data?: unknown }>(path);
        if (res?.data !== undefined) out[path] = res.data;
      } catch {
        /* the widget falls back to a client fetch */
      }
    }),
  );
  return out;
}
