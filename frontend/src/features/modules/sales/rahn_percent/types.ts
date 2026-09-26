export type RahnBilling = 'once' | 'monthly' | 'yearly' | 'custom';

export const RAHN_DURATION_OPTIONS = [6, 9, 12, 18, 24] as const;
export type RahnDuration = (typeof RAHN_DURATION_OPTIONS)[number];

export type RahnWizardStepType =
  | 'business'
  | 'topic'
  | 'domain'
  | 'services'
  | 'deal'
  | 'summary'
  | 'note';

export interface RahnWizardStepConfig {
  id: string;
  type: RahnWizardStepType;
  label: string;
  body?: string;
  sort_order: number;
  active: boolean;
  /** Built-in steps cannot be deleted (only disabled). */
  system?: boolean;
}

export interface RahnTopic {
  id: string;
  name: string;
  description?: string;
  sort_order: number;
  active?: boolean;
}

export interface RahnDomain {
  id: string;
  topic_id: string;
  name: string;
  description?: string;
  /** Suggested revenue share 0–1 (e.g. gold low, cosmetics high). */
  p_suggest: number;
  sort_order: number;
  active?: boolean;
}

export interface RahnServiceCategory {
  id: string;
  name: string;
  description?: string;
  sort_order: number;
  active?: boolean;
  /** Empty = available for all topics. */
  topic_id?: string;
  /** Empty = available for all domains under topic. */
  domain_id?: string;
}

export interface RahnCatalogItem {
  id: string;
  name: string;
  billing: RahnBilling;
  period_months: number;
  renewable: boolean;
  amount: number;
  active: boolean;
  default_selected: boolean;
  /** Display category label (legacy + fallback). */
  category: string;
  category_id?: string;
  description: string;
  sort_order: number;
  /** Mutually exclusive option group (radio). */
  choice_group?: string;
  choice_value?: string;
  /** Extra line for customer (e.g. gateway fee). */
  fee_label?: string;
  /** Show only when this catalog item is selected (empty = always). */
  show_when_item_id?: string;
}

export interface RahnSettings {
  T: number;
  m: number;
  k: number;
  p_min: number;
  p_max: number;
  p_default: number;
  s_hat_default: number;
  clause_template: string;
  duration_options: number[];
  review: {
    enabled: boolean;
    deviation_percent: number;
    consecutive_months: number;
  };
  sales_definition: Record<'G' | 'R' | 'D' | 'X', { enabled: boolean; label: string }>;
  topics: RahnTopic[];
  domains: RahnDomain[];
  categories: RahnServiceCategory[];
  catalog: RahnCatalogItem[];
  wizard_steps: RahnWizardStepConfig[];
}

export interface RahnWizardMeta {
  business_name?: string;
  topic_id?: string;
  domain_id?: string;
}

export interface RahnLockResult {
  C: number;
  V_star: number;
  F_min: number;
  alpha: number;
  F: number;
  p: number;
  p_percent: number;
  V_hat: number;
  S_BE: number | null;
  S_hat: number;
  T: number;
  m: number;
  k: number;
  p_min: number;
  p_max: number;
  breakdown: Array<{
    id: string;
    name: string;
    billing: string;
    amount: number;
    U: number;
    M: number;
    monthly_share: number;
  }>;
  mode: string;
}

export interface RahnPublicPayload {
  F: number;
  p: number;
  p_percent: number;
  V_hat: number;
  S_hat: number;
  alpha: number;
  T: number;
  p_min: number;
  p_max: number;
  F_min: number;
  services: Array<{
    id: string;
    name: string;
    billing: string;
    period_months: number;
    renewable: boolean;
    description: string;
    category?: string;
    fee_label?: string;
  }>;
  clause: string;
}

export interface RahnCalcResponse {
  selected_ids: string[];
  items: RahnCatalogItem[];
  clause: string;
  public: RahnPublicPayload;
  lock: RahnLockResult | null;
  internal?: {
    C: number;
    V_star: number;
    F_min: number;
    S_BE: number | null;
    m: number;
    k: number;
    breakdown: RahnLockResult['breakdown'];
  };
  wizard?: RahnWizardMeta;
}

export interface RahnQuote {
  id: number;
  token: string;
  title: string;
  status: string;
  customer_id: number;
  lead_id: number;
  contract_id: number;
  F: number;
  p: number;
  p_percent: number;
  s_hat: number;
  duration: number;
  locked_at: string | null;
  clause: string;
  share_url: string;
  created_at: string;
  updated_at: string;
  selected_ids: string[];
  wizard?: RahnWizardMeta;
}

export interface RahnContract {
  id: number;
  title: string;
  customer_id: number;
  customer_name: string;
  F: number;
  p: number;
  p_percent: number;
  clause: string;
  duration: number;
  s_hat: number;
  locked_at: string;
}

export interface RahnBill {
  G: number;
  R: number;
  D: number;
  X: number;
  S: number;
  F: number;
  p: number;
  p_share: number;
  V: number;
  C?: number;
  Pi?: number;
}

export const RAHN_API = '/v1/sales/rahn';

export const DEFAULT_WIZARD_STEPS: RahnWizardStepConfig[] = [
  { id: 'business', type: 'business', label: 'کسب‌وکار', sort_order: 1, active: true, system: true },
  { id: 'topic', type: 'topic', label: 'موضوع', sort_order: 2, active: true, system: true },
  { id: 'domain', type: 'domain', label: 'حوزه', sort_order: 3, active: true, system: true },
  { id: 'services', type: 'services', label: 'خدمات', sort_order: 4, active: true, system: true },
  { id: 'deal', type: 'deal', label: 'تعرفه', sort_order: 5, active: true, system: true },
  { id: 'summary', type: 'summary', label: 'خلاصه', sort_order: 6, active: true, system: true },
];

export function clampRahnDuration(value: number, options: number[] = [...RAHN_DURATION_OPTIONS]): number {
  const opts = options.length ? options : [...RAHN_DURATION_OPTIONS];
  if (opts.includes(value)) return value;
  return opts.reduce((best, cur) => (Math.abs(cur - value) < Math.abs(best - value) ? cur : best), opts[0]!);
}

/** Fraction 0–1 → percent number for inputs (0.1 → 10). */
export function fractionToPercentInput(value: number): number {
  return Math.round(value * 10000) / 100;
}

/** Percent input → fraction 0–1 (10 → 0.1). */
export function percentInputToFraction(value: number): number {
  return Math.max(0, Math.min(100, value)) / 100;
}
