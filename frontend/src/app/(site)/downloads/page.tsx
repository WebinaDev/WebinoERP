import { BuilderPageRoute } from '@/themes/webina-corporate-v1/builder/BuilderPageRoute';

export const revalidate = 60;

/** Content is the builder page «downloads» in the database (Pages list); items come from داشبورد ← بازاریابی. */
export default function Page() {
  return <BuilderPageRoute slug="downloads" />;
}
