'use client';

import { MarketingMenusPage as MenuAdminPage } from './menu_admin';
import { CourseStructurePanel } from './course_structure_panel';
import { MarketingResourcePage } from './marketing_resource_page';

export function MarketingPagesPage() {
  return (
    <MarketingResourcePage
      seo="meta"
      titleKey="nav.erp.marketing.pages"
      endpoint="/v1/marketing/pages"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title_fa', labelKey: 'marketing.title' },
        { key: 'published', labelKey: 'marketing.published' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title_fa', labelKey: 'marketing.title' },
        { key: 'title_en', labelKey: 'marketing.titleEn' },
        { key: 'body_fa', labelKey: 'marketing.body', kind: 'textarea' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
      ]}
    />
  );
}

export function MarketingBlogPage() {
  return (
    <MarketingResourcePage
      seo="column"
      titleKey="nav.erp.marketing.blog"
      endpoint="/v1/marketing/blog"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'status', labelKey: 'marketing.status' },
        { key: 'published_at', labelKey: 'marketing.publishedAt' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'category_id', labelKey: 'marketing.topic', kind: 'select', optionsEndpoint: '/v1/marketing/blog/categories' },
        { key: 'status', labelKey: 'marketing.status', kind: 'select', options: [{ value: 'draft', label: 'پیش‌نویس' }, { value: 'published', label: 'منتشرشده' }] },
        { key: 'published_at', labelKey: 'marketing.publishedAt' },
        { key: 'cover_url', labelKey: 'marketing.cover' },
        { key: 'excerpt', labelKey: 'marketing.excerpt', kind: 'textarea' },
        { key: 'body', labelKey: 'marketing.bodyHtml', kind: 'textarea' },
      ]}
    />
  );
}

export function MarketingBlogCategoriesPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.blogCategories"
      endpoint="/v1/marketing/blog/categories"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'name', labelKey: 'marketing.name' },
      ]}
    />
  );
}

export function MarketingMagazinePage() {
  return (
    <MarketingResourcePage
      seo="column"
      titleKey="nav.erp.marketing.magazine"
      endpoint="/v1/marketing/magazine"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'issue', labelKey: 'marketing.issue' },
        { key: 'status', labelKey: 'marketing.status' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'issue', labelKey: 'marketing.issue' },
        { key: 'author', labelKey: 'marketing.author' },
        { key: 'status', labelKey: 'marketing.status', kind: 'select', options: [{ value: 'draft', label: 'پیش‌نویس' }, { value: 'published', label: 'منتشرشده' }] },
        { key: 'published_at', labelKey: 'marketing.publishedAt' },
        { key: 'cover_url', labelKey: 'marketing.cover' },
        { key: 'featured', labelKey: 'marketing.featured', kind: 'checkbox' },
        { key: 'excerpt', labelKey: 'marketing.excerpt', kind: 'textarea' },
        { key: 'body', labelKey: 'marketing.bodyHtml', kind: 'textarea' },
      ]}
    />
  );
}

export function MarketingAcademyPage() {
  return (
    <>
    <MarketingResourcePage
      seo="column"
      titleKey="nav.erp.marketing.academy"
      endpoint="/v1/marketing/academy"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'level', labelKey: 'marketing.level' },
        { key: 'published', labelKey: 'marketing.published' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'level', labelKey: 'marketing.level', kind: 'select', options: [
          { value: 'مبتدی', label: 'مبتدی' }, { value: 'متوسط', label: 'متوسط' }, { value: 'پیشرفته', label: 'پیشرفته' },
        ] },
        { key: 'duration', labelKey: 'marketing.duration' },
        { key: 'instructor', labelKey: 'marketing.instructor' },
        { key: 'cover_url', labelKey: 'marketing.cover' },
        { key: 'enroll_url', labelKey: 'marketing.enrollUrl' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder', kind: 'number' },
        { key: 'description', labelKey: 'marketing.description', kind: 'textarea' },
        { key: 'outcomes', labelKey: 'marketing.outcomes', kind: 'textarea' },
        { key: 'is_free', labelKey: 'marketing.isFree', kind: 'checkbox' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
      ]}
    />
      <CourseStructurePanel />
    </>
  );
}

export function MarketingAcademyLessonsPage() {
  return (
    <MarketingResourcePage
      seo="column"
      titleKey="nav.erp.marketing.academyLessons"
      endpoint="/v1/marketing/academy-lessons"
      columns={[
        { key: 'course_id', labelKey: 'marketing.course' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder' },
      ]}
      fields={[
        { key: 'course_id', labelKey: 'marketing.course', kind: 'select', optionsEndpoint: '/v1/marketing/academy', optionLabel: 'title' },
        { key: 'chapter_id', labelKey: 'marketing.chapter', kind: 'select', optionsEndpoint: '/v1/marketing/academy-chapters', optionLabel: 'title' },
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'summary', labelKey: 'marketing.excerpt', kind: 'textarea' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'video_url', labelKey: 'marketing.videoUrl' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder', kind: 'number' },
        { key: 'content', labelKey: 'marketing.description', kind: 'textarea' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
      ]}
    />
  );
}

export function MarketingPortfolioPage() {
  return (
    <MarketingResourcePage
      seo="column"
      titleKey="nav.erp.marketing.portfolio"
      endpoint="/v1/marketing/portfolio"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'result_metric', labelKey: 'marketing.result' },
        { key: 'featured', labelKey: 'marketing.featured' },
        { key: 'published', labelKey: 'marketing.published' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'category_id', labelKey: 'marketing.category', kind: 'select', optionsEndpoint: '/v1/marketing/portfolio-categories' },
        { key: 'client', labelKey: 'marketing.client' },
        { key: 'cover_url', labelKey: 'marketing.cover' },
        { key: 'result_metric', labelKey: 'marketing.result' },
        { key: 'description', labelKey: 'marketing.description', kind: 'textarea' },
        { key: 'challenge', labelKey: 'marketing.challenge', kind: 'textarea' },
        { key: 'solution', labelKey: 'marketing.solution', kind: 'textarea' },
        { key: 'results', labelKey: 'marketing.results', kind: 'textarea' },
        { key: 'technologies', labelKey: 'marketing.serviceTags', kind: 'lines' },
        { key: 'images', labelKey: 'marketing.gallery', kind: 'lines' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder', kind: 'number' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
        { key: 'featured', labelKey: 'marketing.featured', kind: 'checkbox' },
      ]}
    />
  );
}

export function MarketingPortfolioCategoriesPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.portfolioCategories"
      endpoint="/v1/marketing/portfolio-categories"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'name', labelKey: 'marketing.name' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'name', labelKey: 'marketing.name' },
        { key: 'description', labelKey: 'marketing.description', kind: 'textarea' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder', kind: 'number' },
      ]}
    />
  );
}

export function MarketingMenusPage() {
  return <MenuAdminPage />;
}

export function MarketingFormsPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.forms"
      endpoint="/v1/marketing/forms"
      columns={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'published', labelKey: 'marketing.published' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'description', labelKey: 'marketing.description', kind: 'textarea' },
        { key: 'success_message', labelKey: 'marketing.success' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
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
        { key: 'sort_order', labelKey: 'marketing.sortOrder' },
      ]}
      fields={[
        { key: 'group', labelKey: 'marketing.group', kind: 'select', options: ['عمومی', 'خدمات و پروژه‌ها', 'مشاوره و پروپوزال', 'همکاری با ما', 'پرداخت و قرارداد'].map((v) => ({ value: v, label: v })) },
        { key: 'question', labelKey: 'marketing.question' },
        { key: 'answer', labelKey: 'marketing.answer', kind: 'textarea' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder', kind: 'number' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
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
      seo="column"
      titleKey="nav.erp.marketing.announcements"
      endpoint="/v1/marketing/announcements"
      columns={[
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'type', labelKey: 'marketing.type' },
        { key: 'pinned', labelKey: 'marketing.pinned' },
        { key: 'published', labelKey: 'marketing.published' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'type', labelKey: 'marketing.type', kind: 'select', options: [
          { value: 'event', label: 'رویداد' }, { value: 'update', label: 'به‌روزرسانی' }, { value: 'important', label: 'مهم' }, { value: 'news', label: 'خبر سازمانی' },
        ] },
        { key: 'summary', labelKey: 'marketing.excerpt', kind: 'textarea' },
        { key: 'body', labelKey: 'marketing.bodyHtml', kind: 'textarea' },
        { key: 'starts_at', labelKey: 'marketing.startsAt' },
        { key: 'ends_at', labelKey: 'marketing.endsAt' },
        { key: 'pinned', labelKey: 'marketing.pinned', kind: 'checkbox' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
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
      seo="column"
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
      seo="column"
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
      seo="column"
      titleKey="nav.erp.marketing.downloads"
      endpoint="/v1/marketing/downloads"
      columns={[
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'file_type', labelKey: 'marketing.fileType' },
        { key: 'gated', labelKey: 'marketing.gated' },
        { key: 'published', labelKey: 'marketing.published' },
      ]}
      fields={[
        { key: 'slug', labelKey: 'marketing.slug' },
        { key: 'title', labelKey: 'marketing.title' },
        { key: 'file_type', labelKey: 'marketing.fileType', kind: 'select', options: [
          { value: 'checklist', label: 'چک‌لیست' }, { value: 'template', label: 'قالب' }, { value: 'guide', label: 'راهنما' }, { value: 'worksheet', label: 'ورک‌شیت' },
        ] },
        { key: 'file_url', labelKey: 'marketing.fileUrl' },
        { key: 'related_url', labelKey: 'marketing.relatedUrl' },
        { key: 'sort_order', labelKey: 'marketing.sortOrder', kind: 'number' },
        { key: 'description', labelKey: 'marketing.description', kind: 'textarea' },
        { key: 'gated', labelKey: 'marketing.gated', kind: 'checkbox' },
        { key: 'published', labelKey: 'marketing.published', kind: 'checkbox' },
      ]}
    />
  );
}

export function MarketingSeoRoutesPage() {
  return (
    <MarketingResourcePage
      titleKey="nav.erp.marketing.seoRoutes"
      endpoint="/v1/marketing/seo-routes"
      columns={[
        { key: 'path', labelKey: 'marketing.path' },
        { key: 'title', labelKey: 'marketing.seoTitle' },
        { key: 'noindex', labelKey: 'marketing.noindex' },
      ]}
      fields={[
        { key: 'title', labelKey: 'marketing.seoTitle' },
        { key: 'description', labelKey: 'marketing.seoDescription', kind: 'textarea' },
        { key: 'og_image', labelKey: 'marketing.ogImage' },
        { key: 'canonical', labelKey: 'marketing.canonical' },
        { key: 'noindex', labelKey: 'marketing.noindex', kind: 'checkbox' },
      ]}
    />
  );
}
