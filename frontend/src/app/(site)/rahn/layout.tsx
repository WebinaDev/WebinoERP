import type { ReactNode } from 'react';

/** Private share-token estimates: never indexed. */
export const metadata = { robots: { index: false, follow: false } };

export default function RahnLayout({ children }: { children: ReactNode }) {
  return children;
}
