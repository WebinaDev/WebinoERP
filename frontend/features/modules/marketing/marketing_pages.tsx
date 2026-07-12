'use client';

import { MarketingResourcePage } from './marketing_resource_page';

export function MarketingPagesPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.pages"
      endpoint="/v1/marketing/pages"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title_fa', labelKey: 'marketing.title' },
        { key: 'published', labelKey: 'marketing.published' },
      ]}
    />
  );
}

export function MarketingBlogPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.blog"
      endpoint="/v1/marketing/blog"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'status', labelKey: 'marketing.status' },
      ]}
    />
  );
}

export function MarketingMagazinePage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.magazine"
      endpoint="/v1/marketing/magazine"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'status', labelKey: 'marketing.status' },
      ]}
    />
  );
}

export function MarketingAcademyPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.academy"
      endpoint="/v1/marketing/academy"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'published', labelKey: 'marketing.published' },
      ]}
    />
  );
}

export function MarketingPortfolioPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.portfolio"
      endpoint="/v1/marketing/portfolio"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'client', labelKey: 'marketing.client' },
      ]}
    />
  );
}

export function MarketingFaqPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.faq"
      endpoint="/v1/marketing/faq"
      columns={[
        { key: 'question', labelKey: 'marketing.question' },
        { key: 'group', labelKey: 'marketing.group' },
      ]}
    />
  );
}

export function MarketingTeamPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.team"
      endpoint="/v1/marketing/team"
      columns={[
        { key: 'name', labelKey: 'marketing.name' },
        { key: 'role', labelKey: 'marketing.role' },
      ]}
    />
  );
}

export function MarketingAnnouncementsPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.announcements"
      endpoint="/v1/marketing/announcements"
      columns={[
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'pinned', labelKey: 'marketing.pinned' },
      ]}
    />
  );
}

export function MarketingTestimonialsPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.testimonials"
      endpoint="/v1/marketing/testimonials"
      columns={[
        { key: 'author', labelKey: 'marketing.author' },
        { key: 'company', labelKey: 'marketing.company' },
      ]}
    />
  );
}

export function MarketingServicesPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.services"
      endpoint="/v1/marketing/services"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
      ]}
    />
  );
}

export function MarketingSolutionsPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.solutions"
      endpoint="/v1/marketing/solutions/industries"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'name', labelKey: 'marketing.name' },
      ]}
    />
  );
}

export function MarketingMediaPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.media"
      endpoint="/v1/marketing/media"
      columns={[
        { key: 'path', labelKey: 'marketing.path' },
        { key: 'mime', labelKey: 'marketing.mime' },
      ]}
    />
  );
}

export function MarketingDownloadsPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.downloads"
      endpoint="/v1/marketing/downloads"
      columns={[
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'category', labelKey: 'marketing.category' },
      ]}
    />
  );
}
