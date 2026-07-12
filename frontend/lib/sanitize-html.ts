import DOMPurify from 'dompurify';

/** Client-only HTML sanitizer for ticket bodies (rich text). */
export function sanitizeHtml(html: string): string {
  if (typeof window === 'undefined') {
    return '';
  }
  return DOMPurify.sanitize(html, { USE_PROFILES: { html: true } });
}
