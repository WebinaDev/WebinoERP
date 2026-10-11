import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import '@/themes/webina-corporate-v1/components/connect-hub.css';

export const metadata: Metadata = {
  title: 'ارتباط با وبینا',
  description:
    'خط مستقیم با وبینا: تماس، واتساپ، تلگرام، اینستاگرام، لینکدین و پیام‌رسان‌های ایرانی. آژانس توسعه کسب‌وکار.',
  robots: { index: true, follow: true },
};

export default function ConnectLayout({ children }: { children: ReactNode }) {
  return <div className="min-h-svh">{children}</div>;
}
