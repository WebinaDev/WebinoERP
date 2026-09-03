export type SettingsHubId = 'general' | 'projects' | 'crm' | 'bots' | 'accounting';

/** Internal tab ids used by SettingsPageView */
export const SETTINGS_HUB_TABS: Record<SettingsHubId, string[]> = {
  general: ['auth', 'style', 'visitor', 'raw'],
  projects: ['workflow', 'positions', 'taskcat', 'automations'],
  crm: ['sms', 'notifications', 'canned', 'forms', 'leads', 'coreUpdate'],
  bots: ['bots'],
  accounting: ['payment', 'hosting'],
};

/** Map CRM-style URL segments → internal tab ids */
export const SETTINGS_TAB_ALIASES: Record<string, string> = {
  authentication: 'auth',
  auth: 'auth',
  style: 'style',
  'visitor-tracking': 'visitor',
  visitor_tracking: 'visitor',
  visitor: 'visitor',
  sms: 'sms',
  notifications: 'notifications',
  canned: 'canned',
  canned_responses: 'canned',
  'canned-responses': 'canned',
  forms: 'forms',
  leads: 'leads',
  workflow: 'workflow',
  positions: 'positions',
  taskcat: 'taskcat',
  task_categories: 'taskcat',
  'task-categories': 'taskcat',
  automations: 'automations',
  payment: 'payment',
  'core-update': 'coreUpdate',
  core_update: 'coreUpdate',
  coreUpdate: 'coreUpdate',
  general: 'general',
  raw: 'raw',
  hosting: 'hosting',
  bots: 'bots',
};

export function resolveSettingsTab(raw?: string | null): string | undefined {
  if (!raw) return undefined;
  return SETTINGS_TAB_ALIASES[raw] ?? raw;
}
