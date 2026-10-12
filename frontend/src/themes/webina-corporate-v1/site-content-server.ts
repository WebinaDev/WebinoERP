import { getLocale, getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { homeDefaults, mergeContent, type HomeBlockType, type HomeProps, type RawBlock } from './site-content';

/** One homepage block's props (CMS over defaults) for pages that reuse homepage content. */
export async function getHomeBlock<K extends HomeBlockType>(type: K): Promise<HomeProps[K]> {
  const t = await getTranslations();
  const locale = await getLocale();
  const defaults = homeDefaults(t)[type];
  if (locale !== 'fa') return defaults;
  try {
    const res = await apiServer<{ data: { blocks?: RawBlock[] } }>('/v1/public/home');
    const row = res.data.blocks?.find((b) => b.type === type);
    return mergeContent(defaults, row?.props);
  } catch {
    return defaults;
  }
}
