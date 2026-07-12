import Link from 'next/link';
import { siteHref } from '@/src/lib/api-server';

type ServiceCategory = { slug: string; name: string; children?: { slug: string; name: string }[] };

export function MegaMenuServices({ locale, categories }: { locale: string; categories: ServiceCategory[] }) {
  return (
    <div className="grid gap-4 p-4 md:grid-cols-2 lg:grid-cols-4">
      {categories.map((c) => (
        <div key={c.slug}>
          <Link href={siteHref(undefined, `services/${c.slug}`)} className="font-medium text-[#0066FF] hover:underline">
            {c.name}
          </Link>
          <ul className="mt-2 space-y-1 text-sm text-muted-foreground">
            {c.children?.map((ch) => (
              <li key={ch.slug}>
                <Link href={siteHref(undefined, `services/${ch.slug}`)} className="hover:text-foreground">
                  {ch.name}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
}

export function MegaMenuSolutions({
  locale,
  industries,
}: {
  locale: string;
  industries: { slug: string; name: string }[];
}) {
  return (
    <div className="grid gap-2 p-4 md:grid-cols-2 lg:grid-cols-4">
      {industries.map((ind) => (
        <Link
          key={ind.slug}
          href={siteHref(undefined, `solutions/${ind.slug}`)}
          className="rounded-md px-3 py-2 text-sm hover:bg-muted"
        >
          {ind.name}
        </Link>
      ))}
    </div>
  );
}
