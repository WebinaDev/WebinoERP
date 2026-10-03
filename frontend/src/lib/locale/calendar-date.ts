/**
 * Display dates by UI locale.
 * Date-only `YYYY-MM-DD` values stay calendar dates.
 * Instants are shown in Asia/Tehran for fa and UTC for en.
 * Storage remains Gregorian ISO / UTC.
 */

const JALALI_BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const TEHRAN = 'Asia/Tehran';

export type AppDateLocale = 'fa' | 'en';

type Parts = { y: number; m: number; d: number; hh: number; mm: number };

function div(a: number, b: number): number {
  return Math.trunc(a / b);
}

function mod(a: number, b: number): number {
  return a - Math.trunc(a / b) * b;
}

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

function g2d(gy: number, gm: number, gd: number): number {
  let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
  d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
  return d;
}

function d2g(jdn: number): { gy: number; gm: number; gd: number } {
  let j = 4 * jdn + 139361631;
  j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
  const i = div(mod(j, 1461), 4) * 5 + 308;
  const gd = div(mod(i, 153), 5) + 1;
  const gm = mod(div(i, 153), 12) + 1;
  const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
  return { gy, gm, gd };
}

function jalaliCalendar(jy: number): { leap: number; gy: number; march: number } {
  const breaks = JALALI_BREAKS;
  const bl = breaks.length;
  const gy = jy + 621;
  let leapJ = -14;
  let jp = breaks[0];
  let jump = 0;
  if (jy < jp || jy >= breaks[bl - 1]) {
    throw new Error(`Invalid Jalali year ${jy}`);
  }
  for (let i = 1; i < bl; i += 1) {
    const jm = breaks[i];
    jump = jm - jp;
    if (jy < jm) break;
    leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
    jp = jm;
  }
  let n = jy - jp;
  leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
  if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
  const march = 20 + leapJ - leapG;
  if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
  let leap = mod(mod(n + 1, 33) - 1, 4);
  if (leap === -1) leap = 4;
  return { leap, gy, march };
}

function j2d(jy: number, jm: number, jd: number): number {
  const r = jalaliCalendar(jy);
  return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
}

function d2j(jdn: number): { jy: number; jm: number; jd: number } {
  const gy = d2g(jdn).gy;
  let jy = gy - 621;
  const r = jalaliCalendar(jy);
  const jdn1f = g2d(gy, 3, r.march);
  let k = jdn - jdn1f;
  if (k >= 0) {
    if (k <= 185) {
      return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 };
    }
    k -= 186;
  } else {
    jy -= 1;
    k += 179;
    if (r.leap === 1) k += 1;
  }
  return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 };
}

export function toJalali(gy: number, gm: number, gd: number): { jy: number; jm: number; jd: number } {
  return d2j(g2d(gy, gm, gd));
}

export function toGregorian(jy: number, jm: number, jd: number): { gy: number; gm: number; gd: number } {
  return d2g(j2d(jy, jm, jd));
}

export function toPersianDigits(value: string): string {
  return value.replace(/\d/g, (digit) => FA_DIGITS[Number(digit)] ?? digit);
}

export function gregorianParts(iso: string, timeZone: string): Parts | null {
  const trimmed = iso.trim();
  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed);
  if (dateOnly) {
    return { y: Number(dateOnly[1]), m: Number(dateOnly[2]), d: Number(dateOnly[3]), hh: 0, mm: 0 };
  }
  const native = new Date(trimmed.includes(' ') && !trimmed.includes('T') ? trimmed.replace(' ', 'T') + (trimmed.endsWith('Z') ? '' : 'Z') : trimmed);
  if (Number.isNaN(native.getTime())) return null;
  const fmt = new Intl.DateTimeFormat('en-US', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  });
  const bag = Object.fromEntries(fmt.formatToParts(native).map((part) => [part.type, part.value]));
  let hour = Number(bag.hour);
  if (hour === 24) hour = 0;
  return {
    y: Number(bag.year),
    m: Number(bag.month),
    d: Number(bag.day),
    hh: hour,
    mm: Number(bag.minute),
  };
}

export function displayDateKey(iso: string, locale: AppDateLocale = 'fa'): string {
  const parts = gregorianParts(iso, locale === 'fa' ? TEHRAN : 'UTC');
  if (!parts) return '';
  return `${parts.y}-${pad(parts.m)}-${pad(parts.d)}`;
}

export function formatCalendarDate(iso: string, locale: AppDateLocale = 'fa', includeTime = false): string {
  if (!iso) return '—';
  const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(iso.trim());
  const parts = gregorianParts(iso, locale === 'fa' ? TEHRAN : 'UTC');
  if (!parts) return '—';
  if (locale === 'fa') {
    const j = toJalali(parts.y, parts.m, parts.d);
    let text = `${j.jy}/${pad(j.jm)}/${pad(j.jd)}`;
    if (includeTime && !dateOnly) text += ` ${pad(parts.hh)}:${pad(parts.mm)}`;
    return toPersianDigits(text);
  }
  let text = `${parts.y}-${pad(parts.m)}-${pad(parts.d)}`;
  if (includeTime && !dateOnly) text += ` ${pad(parts.hh)}:${pad(parts.mm)}`;
  return text;
}
