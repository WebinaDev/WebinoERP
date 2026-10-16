import { dynamicIconImports } from 'lucide-react/dynamic';

// Serves any lucide icon as a static SVG (/lucide-icon/<PascalName>.svg) so client
// components can show icons chosen in the admin without bundling all of lucide.
export const dynamic = 'force-dynamic';

type IconNode = [string, Record<string, string | number>][];
const imports = dynamicIconImports as unknown as Record<string, () => Promise<{ __iconNode: IconNode }>>;
const pascalToKebab = new Map<string, string>(
  Object.keys(imports).map((k) => [k.split('-').map((p) => p.charAt(0).toUpperCase() + p.slice(1)).join(''), k]),
);

const esc = (v: string | number) => String(v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

export async function GET(_req: Request, ctx: { params: Promise<{ name: string }> }) {
  const { name } = await ctx.params;
  const key = decodeURIComponent(name).replace(/\.svg$/, '');
  const kebab = pascalToKebab.get(key) ?? (key in imports ? key : undefined);
  if (!kebab) {
    return new Response('not found', { status: 404, headers: { 'Cache-Control': 'public, max-age=3600' } });
  }
  const { __iconNode } = await imports[kebab]();
  const body = __iconNode
    .map(([tag, attrs]) => `<${tag} ${Object.entries(attrs).filter(([k]) => k !== 'key').map(([k, v]) => `${k}="${esc(v)}"`).join(' ')}/>`)
    .join('');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${body}</svg>`;
  return new Response(svg, {
    headers: {
      'Content-Type': 'image/svg+xml; charset=utf-8',
      'Cache-Control': 'public, max-age=604800, stale-while-revalidate=86400',
    },
  });
}
