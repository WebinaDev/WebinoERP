import apiClient from '@/lib/api-client';

export type AiOverview = {
  jobs_pending: number;
  jobs_failed: number;
  jobs_done: number;
  jobs_cost_toman?: number;
  calendar_upcoming: number;
  incomplete_products: number;
  sample_incomplete: { id: number; name: string; missing: string[] }[];
  settings: AiSettings;
  module_enabled: boolean;
};

export type AiSettings = Record<string, unknown> & {
  default_provider?: string;
  gapgpt_model?: string;
  openai_model?: string;
  site_name?: string;
  site_topic?: string;
  site_description?: string;
  tone?: string;
  language?: string;
  enabled?: boolean;
  queue_paused?: boolean;
  do_product?: boolean;
  do_blog?: boolean;
  do_page?: boolean;
  daily_blog_quota?: number;
  daily_product_quota?: number;
  auto_publish?: boolean;
  prompt_system?: string;
  prompt_product?: string;
  prompt_blog?: string;
  usd_to_toman?: number;
  has_gapgpt_key?: boolean;
  has_openai_key?: boolean;
};

export type AiJob = {
  id: number;
  job_type: string;
  target_type: string;
  target_id: number;
  target_title?: string;
  status: string;
  provider: string;
  model?: string;
  error_message: string;
  result_summary: string;
  attempts: number;
  tokens_in: number;
  tokens_out: number;
  cost_toman?: number;
  created_at: string;
  started_at: string | null;
  updated_at: string;
  finished_at: string | null;
};

export type CalendarSlot = {
  id: number;
  slot_date: string;
  content_type: string;
  topic: string;
  focus_keyword: string;
  secondary_keywords: string;
  category_id: number;
  product_id: number;
  status: string;
  notes: string;
};

export type AiProposal = {
  id: number;
  kind: 'title' | 'catalog';
  product_id: number;
  product_name: string;
  current: Record<string, unknown>;
  proposed: Record<string, unknown>;
  status: string;
  created_at: string;
  updated_at: string;
};

export type AiPageRow = {
  id: number;
  title: string;
  status: string;
  modified: string;
  url: string;
  page_prompt: string;
  has_elementor: boolean;
  elementor_url: string;
};

function dataOf<T>(res: { data: T }): T {
  return res.data;
}

export async function fetchAiOverview() {
  return dataOf(await apiClient.get<AiOverview>('/v1/ai-content/overview'));
}

export async function fetchAiSettings() {
  return dataOf(await apiClient.get<AiSettings>('/v1/ai-content/settings'));
}

export async function saveAiSettings(body: Partial<AiSettings>) {
  return dataOf(await apiClient.post<AiSettings>('/v1/ai-content/settings', body));
}

export async function fetchAiJobs(params?: { status?: string; limit?: number }) {
  return dataOf(
    await apiClient.get<{ items: AiJob[]; total: number }>('/v1/ai-content/jobs', { params }),
  );
}

export async function retryAiJob(id: number) {
  return dataOf(await apiClient.post<{ ok: boolean }>(`/v1/ai-content/jobs/${id}/retry`));
}

export async function cancelAiJob(id: number) {
  return dataOf(await apiClient.post<{ ok: boolean; job: AiJob }>(`/v1/ai-content/jobs/${id}/cancel`));
}

export async function cancelPendingAiJobs() {
  return dataOf(await apiClient.post<{ ok: boolean; count: number }>('/v1/ai-content/jobs/cancel-pending'));
}

export async function runDueJobs(limit = 1) {
  return dataOf(
    await apiClient.post<{ processed: number[]; count: number; paused?: boolean }>(
      '/v1/ai-content/jobs/run-due',
      { limit },
    ),
  );
}

export async function fetchAiQueue() {
  return dataOf(await apiClient.get<{ paused: boolean }>('/v1/ai-content/queue'));
}

export async function setAiQueuePaused(paused: boolean) {
  return dataOf(await apiClient.post<{ ok: boolean; paused: boolean }>('/v1/ai-content/queue', { paused }));
}

export async function generateAi(body: Record<string, unknown>) {
  return dataOf(
    await apiClient.post<{ ok: boolean; job_id: number; queued?: boolean; job?: AiJob }>(
      '/v1/ai-content/generate',
      body,
    ),
  );
}

export async function fetchIncompleteProducts(limit = 50) {
  return dataOf(
    await apiClient.get<{ items: { id: number; name: string; missing: string[] }[]; total: number }>(
      '/v1/ai-content/products/incomplete',
      { params: { limit } },
    ),
  );
}

export async function createAiProduct(body: { name: string; missing?: string[] }) {
  return dataOf(await apiClient.post('/v1/ai-content/products', body));
}

export async function fillProductsBatch(ids?: number[]) {
  return dataOf(
    await apiClient.post<{ ok: boolean; job_ids: number[]; count: number }>(
      '/v1/ai-content/products/fill-batch',
      ids ? { ids } : {},
    ),
  );
}

export async function fetchCalendar(from?: string, to?: string) {
  return dataOf(
    await apiClient.get<{ items: CalendarSlot[] }>('/v1/ai-content/calendar', {
      params: { from, to },
    }),
  );
}

export async function createCalendarSlot(body: Partial<CalendarSlot>) {
  return dataOf(await apiClient.post<CalendarSlot>('/v1/ai-content/calendar', body));
}

export async function bulkCalendar(body: {
  topics: string;
  start_date: string;
  content_type: string;
  focus_keyword?: string;
}) {
  return dataOf(
    await apiClient.post<{ created: number; items: CalendarSlot[] }>('/v1/ai-content/calendar/bulk', body),
  );
}

export async function deleteCalendarSlot(id: number) {
  return dataOf(await apiClient.delete<{ ok: boolean }>(`/v1/ai-content/calendar/${id}`));
}

export async function runCalendarDue() {
  return dataOf(await apiClient.post<{ ok: boolean }>('/v1/ai-content/calendar/run-due'));
}

export async function fetchAiPages(page = 1, search = '') {
  return dataOf(
    await apiClient.get<{ items: AiPageRow[]; page: number; found: number; elementor: boolean }>(
      '/v1/ai-content/pages',
      { params: { page, per_page: 50, search: search || undefined } },
    ),
  );
}

export async function createAiPage(body: { title: string; page_prompt?: string }) {
  return dataOf(await apiClient.post('/v1/ai-content/pages', body));
}

export async function updateAiPage(id: number, body: Partial<AiPageRow>) {
  return dataOf(await apiClient.patch(`/v1/ai-content/pages/${id}`, body));
}

export async function fetchAiProposals(kind: 'title' | 'catalog', status = 'pending', limit = 100) {
  return dataOf(
    await apiClient.get<{ items: AiProposal[]; total: number }>(`/v1/ai-content/proposals/${kind}`, {
      params: { status, limit },
    }),
  );
}

export async function enqueueAiProposals(kind: 'title' | 'catalog', ids?: number[]) {
  return dataOf(
    await apiClient.post<{ ok: boolean; job_ids: number[]; count: number; chunks: number }>(
      `/v1/ai-content/proposals/${kind}/enqueue`,
      ids ? { ids } : {},
    ),
  );
}

export async function applyAiProposal(id: number, body?: { name?: string; proposed?: Record<string, unknown> }) {
  return dataOf(await apiClient.post<AiProposal>(`/v1/ai-content/proposals/${id}/apply`, body ?? {}));
}

export async function skipAiProposal(id: number) {
  return dataOf(await apiClient.post<AiProposal>(`/v1/ai-content/proposals/${id}/skip`, {}));
}

export async function requeueAiProposal(kind: 'title' | 'catalog', productId: number) {
  return dataOf(
    await apiClient.post<{ ok: boolean; count: number }>(
      `/v1/ai-content/proposals/${kind}/product/${productId}/requeue`,
      {},
    ),
  );
}

export async function suggestCategories(kind: 'blog' | 'product') {
  return dataOf(await apiClient.post<{ ok: boolean; job_id: number }>('/v1/ai-content/suggest-categories', { kind }));
}

export async function getCategorySuggestions(kind: 'blog' | 'product') {
  return dataOf(
    await apiClient.get<{ kind: string; suggestions: { categories?: { name?: string }[] } | null }>(
      `/v1/ai-content/suggest-categories/${kind}`,
    ),
  );
}

export async function applyCategorySuggestions(kind: 'blog' | 'product') {
  return dataOf(
    await apiClient.post<{ ok: boolean; count: number }>(`/v1/ai-content/suggest-categories/${kind}/apply`, {}),
  );
}

export async function fillTermsBatch(taxonomy: 'product_cat' | 'product_brand' | 'category', ids?: number[]) {
  return dataOf(
    await apiClient.post<{ ok: boolean; count: number }>('/v1/ai-content/terms/fill-batch', { taxonomy, ids }),
  );
}

export async function fetchAttrTemplates() {
  return dataOf(
    await apiClient.get<{
      items: {
        id: number;
        product_cat_id: number;
        category_name: string;
        attribute_ids: number[];
        labels: { attribute_id?: number; label: string; slug?: string; options?: string[] }[];
      }[];
    }>('/v1/ai-content/attribute-templates'),
  );
}

export async function suggestAttrTemplate(catId: number) {
  return dataOf(
    await apiClient.post<{ ok: boolean; job_id: number }>(`/v1/ai-content/attribute-templates/${catId}/suggest`),
  );
}

export async function fetchAttrDraft(catId: number) {
  return dataOf(
    await apiClient.get<{
      product_cat_id: number;
      draft: { attributes: { label: string; slug: string; options: string[] }[] } | null;
      template: { attribute_ids?: number[]; labels?: unknown[] } | null;
      discovered: unknown[];
    }>(`/v1/ai-content/attribute-templates/${catId}/draft`),
  );
}

export async function confirmAttrTemplate(product_cat_id: number, draft: unknown) {
  return dataOf(await apiClient.post('/v1/ai-content/attribute-templates', { product_cat_id, draft }));
}

export async function deleteAttrTemplate(catId: number) {
  return dataOf(await apiClient.delete<{ ok: boolean }>(`/v1/ai-content/attribute-templates/${catId}`));
}

export async function fetchAiCostEstimate(draft?: Partial<AiSettings>) {
  if (draft && Object.keys(draft).length > 0) {
    return dataOf(await apiClient.post('/v1/ai-content/cost-estimate', draft));
  }
  return dataOf(await apiClient.get('/v1/ai-content/cost-estimate'));
}
