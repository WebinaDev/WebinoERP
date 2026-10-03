const DIGITS: Record<string, string> = {
  '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
  '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
};

export function normalizeIranianNationalId(raw: string): string {
  return raw.replace(/[۰-۹٠-٩]/g, (ch) => DIGITS[ch] ?? ch).replace(/\D/g, '');
}

/** 10-digit Iranian national ID check digit. */
export function isValidIranianNationalId(raw: string): boolean {
  const code = normalizeIranianNationalId(raw);
  if (!/^\d{10}$/.test(code)) return false;
  if (/^(\d)\1{9}$/.test(code)) return false;
  const sum = code
    .slice(0, 9)
    .split('')
    .reduce((acc, digit, index) => acc + Number(digit) * (10 - index), 0);
  const rem = sum % 11;
  const check = Number(code[9]);
  return rem < 2 ? check === rem : check === 11 - rem;
}
