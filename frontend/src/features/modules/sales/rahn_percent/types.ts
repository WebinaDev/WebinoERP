export type RahnBilling = 'once' | 'monthly' | 'yearly' | 'custom';

export interface RahnCatalogItem {
  id: string;
  name: string;
  billing: RahnBilling;
  period_months: number;
  renewable: boolean;
  amount: number;
  active: boolean;
  default_selected: boolean;
  category: string;
  description: string;
  sort_order: number;
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
  review: {
    enabled: boolean;
    deviation_percent: number;
    consecutive_months: number;
  };
  sales_definition: Record<'G' | 'R' | 'D' | 'X', { enabled: boolean; label: string }>;
  catalog: RahnCatalogItem[];
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
