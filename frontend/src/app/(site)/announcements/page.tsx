import { BuilderPageRoute, builderPageMetadata } from '@/themes/webina-corporate-v1/builder/BuilderPageRoute';

export const revalidate = 60;

export function generateMetadata() {
  return builderPageMetadata('announcements');
}

/** Content is the builder page «announcements» in the database (Pages list); items come from داشبورد ← بازاریابی. */
export default function Page() {
  return <BuilderPageRoute slug="announcements" />;
}
