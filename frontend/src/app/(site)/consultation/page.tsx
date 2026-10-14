import { BuilderPageRoute, builderPageMetadata } from '@/themes/webina-corporate-v1/builder/BuilderPageRoute';

export const revalidate = 60;

export function generateMetadata() {
  return builderPageMetadata('consultation');
}

/** Content is the builder page «consultation» in the database (Pages list); nothing is hardcoded here. */
export default function Page() {
  return <BuilderPageRoute slug="consultation" />;
}
