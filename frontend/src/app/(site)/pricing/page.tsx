import { BuilderPageRoute, builderPageMetadata } from '@/themes/webina-corporate-v1/builder/BuilderPageRoute';

export const revalidate = 60;

export function generateMetadata() {
  return builderPageMetadata('pricing');
}

/** Content is the builder page «pricing» in the database (Pages list); nothing is hardcoded here. */
export default function Page() {
  return <BuilderPageRoute slug="pricing" />;
}
