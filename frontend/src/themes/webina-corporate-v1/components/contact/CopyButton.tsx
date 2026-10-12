'use client';

import { useState } from 'react';
import { Check, Copy } from 'lucide-react';

export function CopyButton({ value, label, copiedLabel }: { value: string; label: string; copiedLabel: string }) {
  const [done, setDone] = useState(false);

  async function copy() {
    try {
      await navigator.clipboard.writeText(value);
    } catch {
      const el = document.createElement('textarea');
      el.value = value;
      document.body.appendChild(el);
      el.select();
      document.execCommand('copy');
      el.remove();
    }
    setDone(true);
    window.setTimeout(() => setDone(false), 1800);
  }

  return (
    <button type="button" className={`cc-copy${done ? ' is-done' : ''}`} onClick={() => void copy()} aria-label={`${label}: ${value}`}>
      {done ? <Check className="size-3.5" aria-hidden /> : <Copy className="size-3.5" aria-hidden />}
      <span aria-live="polite">{done ? copiedLabel : label}</span>
    </button>
  );
}
