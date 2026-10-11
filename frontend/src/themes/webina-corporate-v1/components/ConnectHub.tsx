'use client';

import { useEffect, useState } from 'react';

import { BrandMark, BrandWordmark } from './BrandMark';
import { ConnectBrandIcon, type BrandKind } from './ConnectBrandIcon';

type Channel = {
  id: string;
  label: string;
  value: string;
  href: string;
  hint?: string;
  icon: BrandKind;
  copy?: string;
  featured?: boolean;
};

type Section = {
  id: string;
  title: string;
  items: Channel[];
};

const SECTIONS: Section[] = [
  {
    id: 'direct',
    title: 'تماس مستقیم',
    items: [
      {
        id: 'phone-land',
        label: 'تلفن ثابت',
        value: '021-91017441',
        href: 'tel:+982191017441',
        icon: 'phone',
        copy: '02191017441',
        featured: true,
      },
      {
        id: 'phone-mobile',
        label: 'موبایل / پشتیبانی',
        value: '09925339475',
        href: 'tel:+989925339475',
        icon: 'phone',
        copy: '09925339475',
        featured: true,
      },
      {
        id: 'whatsapp-mobile',
        label: 'واتساپ',
        value: '09925339475',
        href: 'https://wa.me/989925339475',
        icon: 'whatsapp',
        copy: '09925339475',
      },
      {
        id: 'whatsapp-land',
        label: 'واتساپ ثابت',
        value: '02191017441',
        href: 'https://wa.me/982191017441',
        icon: 'whatsapp',
        copy: '02191017441',
      },
    ],
  },
  {
    id: 'social',
    title: 'شبکه‌ها و پشتیبانی',
    items: [
      {
        id: 'instagram',
        label: 'اینستاگرام',
        value: '@WebinaAgency',
        href: 'https://instagram.com/WebinaAgency',
        icon: 'instagram',
        copy: 'WebinaAgency',
      },
      {
        id: 'telegram-support',
        label: 'پشتیبانی تلگرام',
        value: 't.me/webinasupport',
        href: 'https://t.me/webinasupport',
        icon: 'telegram',
        copy: 'webinasupport',
      },
      {
        id: 'telegram-channel',
        label: 'کانال تلگرام',
        value: '@WebinaAgency',
        href: 'https://t.me/WebinaAgency',
        icon: 'telegram',
        copy: 'WebinaAgency',
      },
      {
        id: 'linkedin',
        label: 'لینکدین',
        value: 'company/webinadev',
        href: 'https://ir.linkedin.com/company/webinadev',
        icon: 'linkedin',
        copy: 'webinadev',
      },
      {
        id: 'website',
        label: 'وب‌سایت رسمی',
        value: 'webina.dev',
        href: 'https://webina.dev',
        icon: 'web',
        copy: 'webina.dev',
      },
    ],
  },
  {
    id: 'iran',
    title: 'پیام‌رسان‌های ایرانی',
    items: [
      {
        id: 'bale',
        label: 'بله',
        value: '@WebinaCo',
        href: 'https://ble.ir/WebinaCo',
        icon: 'bale',
        copy: 'WebinaCo',
      },
      {
        id: 'eitaa',
        label: 'ایتا',
        value: '09925339475',
        href: 'https://eitaa.com/sms?phone=09925339475',
        icon: 'eitaa',
        copy: '09925339475',
      },
      {
        id: 'rubika',
        label: 'روبیکا',
        value: '09925339475',
        href: 'https://rubika.ir',
        hint: 'جستجو با شماره',
        icon: 'rubika',
        copy: '09925339475',
      },
      {
        id: 'gap',
        label: 'گپ',
        value: '09925339475',
        href: 'tel:+989925339475',
        icon: 'gap',
        copy: '09925339475',
      },
      {
        id: 'igap',
        label: 'آیگپ',
        value: '09925339475',
        href: 'tel:+989925339475',
        icon: 'igap',
        copy: '09925339475',
      },
    ],
  },
];

function CopyIcon({ done }: { done: boolean }) {
  if (done) {
    return (
      <svg viewBox="0 0 24 24" className="ch-copy-glyph" aria-hidden>
        <path
          fill="currentColor"
          d="M9.5 16.2 5.3 12l1.4-1.4 2.8 2.8 7.1-7.1L18 7.7z"
        />
      </svg>
    );
  }
  return (
    <svg viewBox="0 0 24 24" className="ch-copy-glyph" aria-hidden>
      <path
        fill="currentColor"
        d="M16 1H4a2 2 0 00-2 2v12h2V3h12V1zm3 4H8a2 2 0 00-2 2v14a2 2 0 002 2h11a2 2 0 002-2V7a2 2 0 00-2-2zm0 16H8V7h11v14z"
      />
    </svg>
  );
}

function ChannelRow({
  ch,
  index,
  copied,
  onCopy,
}: {
  ch: Channel;
  index: number;
  copied: string | null;
  onCopy: (id: string, value: string) => void;
}) {
  const isCopied = copied === ch.id;
  return (
    <li className={`ch-row${ch.featured ? ' is-featured' : ''}`} style={{ ['--i' as string]: String(index) }}>
      <a className="ch-link" href={ch.href} target={ch.href.startsWith('tel:') ? undefined : '_blank'} rel="noreferrer">
        <ConnectBrandIcon kind={ch.icon} />
        <span className="ch-meta">
          <span className="ch-label">{ch.label}</span>
          <span className="ch-value" dir="ltr">
            {ch.value}
          </span>
          {ch.hint ? <span className="ch-hint">{ch.hint}</span> : null}
        </span>
        <span className="ch-chev" aria-hidden>
          <svg viewBox="0 0 24 24">
            <path fill="currentColor" d="M15.4 12 9.7 6.3l1.4-1.4L18.2 12l-7.1 7.1-1.4-1.4z" />
          </svg>
        </span>
      </a>
      <button
        type="button"
        className={`ch-copy${isCopied ? ' is-done' : ''}`}
        onClick={() => onCopy(ch.id, ch.copy ?? ch.value)}
        aria-label={isCopied ? 'کپی شد' : `کپی ${ch.label}`}
      >
        <CopyIcon done={isCopied} />
        <span className="ch-copy-text">{isCopied ? 'کپی شد' : 'کپی'}</span>
      </button>
    </li>
  );
}

export function ConnectHub() {
  const [ready, setReady] = useState(false);
  const [copied, setCopied] = useState<string | null>(null);

  useEffect(() => {
    const id = window.requestAnimationFrame(() => setReady(true));
    return () => window.cancelAnimationFrame(id);
  }, []);

  async function copyValue(id: string, value: string) {
    try {
      await navigator.clipboard.writeText(value.replace(/^@/, ''));
      setCopied(id);
      window.setTimeout(() => setCopied((cur) => (cur === id ? null : cur)), 1600);
    } catch {
      /* ignore */
    }
  }

  let stagger = 0;

  return (
    <div className={`ch-page${ready ? ' is-ready' : ''}`}>
      <div className="ch-atmos" aria-hidden>
        <div className="ch-orb ch-orb-a" />
        <div className="ch-orb ch-orb-b" />
        <div className="ch-grid" />
      </div>

      <div className="ch-shell">
        {/* Brand / hero — stacked on mobile, sticky side panel on desktop */}
        <aside className="ch-aside">
          <div className="ch-aside-inner">
            <a href="https://webina.dev" className="ch-brand" aria-label="وبینا، آژانس توسعه کسب‌وکار">
              <span className="ch-brand-ring" aria-hidden />
              <BrandMark className="ch-mark" title="وبینا" />
              <BrandWordmark className="ch-word" />
            </a>

            <p className="ch-kicker">آژانس توسعه کسب‌وکار</p>
            <h1 className="ch-title">
              خط مستقیم
              <span> با وبینا</span>
            </h1>
            <p className="ch-lead">
              پشتیبانی، شبکه‌های اجتماعی و کانال‌های رسمی برای همکاری، مشاوره و پیگیری پروژه.
            </p>

            <div className="ch-quick">
              <a className="ch-quick-btn is-primary" href="tel:+982191017441">
                <ConnectBrandIcon kind="phone" />
                <span>
                  <strong>تماس فوری</strong>
                  <em dir="ltr">021-91017441</em>
                </span>
              </a>
              <a className="ch-quick-btn" href="https://wa.me/989925339475" target="_blank" rel="noreferrer">
                <ConnectBrandIcon kind="whatsapp" />
                <span>
                  <strong>واتساپ</strong>
                  <em dir="ltr">09925339475</em>
                </span>
              </a>
            </div>

            <a className="ch-site-pill" href="https://webina.dev">
              <ConnectBrandIcon kind="web" />
              <span>مشاهدهٔ webina.dev</span>
            </a>
          </div>
        </aside>

        {/* Channels — single column mobile, denser board on desktop */}
        <main className="ch-main">
          {SECTIONS.map((section) => (
            <section key={section.id} className="ch-section" aria-labelledby={`ch-h-${section.id}`}>
              <h2 id={`ch-h-${section.id}`} className="ch-section-title">
                {section.title}
              </h2>
              <ul className="ch-list">
                {section.items.map((ch) => {
                  const i = stagger++;
                  return (
                    <ChannelRow key={ch.id} ch={ch} index={i} copied={copied} onCopy={copyValue} />
                  );
                })}
              </ul>
            </section>
          ))}

          <footer className="ch-foot">
            <p>وبینا؛ طراحی، محصول و اسکیل کسب‌وکارهای دیجیتال</p>
          </footer>
        </main>
      </div>
    </div>
  );
}
