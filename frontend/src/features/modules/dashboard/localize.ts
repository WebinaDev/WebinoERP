const STATUS_KEYS = new Set([
  'open',
  'done',
  'completed',
  'cancelled',
  'closed',
  'failed',
  'ready',
  'pending',
  'active',
  'in_progress',
  'draft',
  'ssl_pending',
  'provisioning',
  'new',
  'converted',
  'approved',
  'rejected',
  'resolved',
  'paid',
  'void',
  'follow_up',
  'error',
  'license_expired',
  'unassigned',
  'unknown',
]);

type Translate = (key: string) => string;

export function localizeToken(value: string, t: Translate, formatDigits: (value: string) => string): string {
  const customer = /^__customer__:(\d+)$/.exec(value) ?? /^Customer #(\d+)$/.exec(value);
  if (customer) {
    return `${t('badges.customer')} ${formatDigits(customer[1])}`;
  }
  const token = value.trim();
  const slug = token.replace(/^__|__$/g, '').toLowerCase();
  if (token === '__unassigned__' || token === 'Unassigned') return t('badges.unassigned');
  if (token === '__unknown__' || token === 'Unknown') return t('badges.unknown');
  if (STATUS_KEYS.has(slug) || STATUS_KEYS.has(token)) {
    const label = t(`badges.${slug || token}`);
    return label.startsWith('badges.') ? value : label;
  }
  return formatDigits(value);
}

export function localizeReasons(reasons: string[] | string, t: Translate): string {
  const list = Array.isArray(reasons) ? reasons : String(reasons).split(',').filter(Boolean);
  return list
    .map((reason) => {
      const label = t(`badges.${reason.trim()}`);
      return label.startsWith('badges.') ? reason : label;
    })
    .join('، ');
}
