import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { apiServer, siteHref } from '@/lib/public-api-server';

export const revalidate = 60;

type ServiceCategory = {
  id: number;
  slug: string;
  name: string;
  services?: { slug: string; title: string }[];
};

export default async function ServicesPage() {
  const t = await getTranslations();
  let categories: ServiceCategory[] = [];
  try {
    const res = await apiServer<{ data: ServiceCategory[] }>('/v1/public/services');
    categories = res.data ?? [];
  } catch {
    categories = [];
  }

  return (
    <div className="bg-[#07070a] text-white">
      <header className="border-b border-white/10 px-4 py-16 lg:px-6">
        <div className="mx-auto max-w-7xl">
          <p className="text-xs tracking-[0.22em] text-[#9cc4ff]">{t('site.nav.services')}</p>
          <h1 className="mt-3 text-4xl font-black sm:text-5xl">{t('site.landing.servicesTitle')}</h1>
          <p className="mt-4 max-w-2xl text-white/60">{t('site.landing.servicesLead')}</p>
        </div>
      </header>
      <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 lg:px-6">
        {categories.map((category) => (
          <section key={category.id} className="rounded-3xl border border-white/10 p-6">
            <h2 className="text-2xl font-semibold">
              <Link href={siteHref(undefined, `services/${category.slug}`)} className="hover:text-[#6ea8ff]">{category.name}</Link>
            </h2>
            <ul className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {category.services?.map((service) => (
                <li key={service.slug}>
                  <Link href={siteHref(undefined, `services/${service.slug}`)} className="block rounded-2xl bg-white/[0.03] px-4 py-3 text-sm hover:bg-[#0066FF]/15">
                    {service.title}
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    </div>
  );
}
