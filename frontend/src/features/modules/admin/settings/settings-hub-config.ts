export type SettingsHubId = 'general' | 'projects' | 'crm' | 'bots' | 'accounting';

export const SETTINGS_HUB_TABS: Record<SettingsHubId, string[]> = {
  general: ['general', 'style', 'notifications', 'auth', 'sms', 'payment', 'raw'],
  projects: ['positions', 'taskcat'],
  crm: ['canned'],
  bots: ['bots'],
  accounting: ['payment', 'hosting'],
};
