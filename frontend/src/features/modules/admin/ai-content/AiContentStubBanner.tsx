'use client';

export function AiContentStubBanner() {
  return (
    <div className="mb-4 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:text-amber-100">
      AI Content runs in preview mode: backend providers are stubbed until real generation is configured.
    </div>
  );
}
