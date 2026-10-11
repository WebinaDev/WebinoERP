'use client';

import { useEffect, useState } from 'react';
import {
  AtSign,
  Camera,
  ExternalLink,
  Globe,
  MessageCircle,
  Phone,
  Send,
  Users,
} from 'lucide-react';

import { BrandMark, BrandWordmark } from './BrandMark';

type Channel = {
  id: string;
  label: string;
  value: string;
  href: string;
  hint?: string;
  icon: 'phone' | 'instagram' | 'telegram' | 'whatsapp' | 'bale' | 'eitaa' | 'rubika' | 'gap' | 'igap' | 'linkedin' | 'web' | 'at';
};

const CHANNELS: Channel[] = [
  {
    id: 'phone-land',
    label: 'تلفن ثابت',
    value: '021-91017441',
    href: 'tel:+982191017441',
    icon: 'phone',
  },
  {
    id: 'phone-mobile',
    label: 'موبایل',
    value: '09925339475',
    href: 'tel:+989925339475',
    icon: 'phone',
  },
  {
    id: 'instagram',
    label: 'اینستاگرام',
    value: '@WebinaAgency',
    href: 'https://instagram.com/WebinaAgency',
    icon: 'instagram',
  },
  {
    id: 'telegram-support',
    label: 'پشتیبانی تلگرام',
    value: 't.me/webinasupport',
    href: 'https://t.me/webinasupport',
    icon: 'telegram',
  },
  {
    id: 'telegram-channel',
    label: 'کانال تلگرام',
    value: '@WebinaAgency',
    href: 'https://t.me/WebinaAgency',
    icon: 'telegram',
  },
  {
    id: 'whatsapp-land',
    label: 'واتساپ',
    value: '02191017441',
    href: 'https://wa.me/982191017441',
    icon: 'whatsapp',
  },
  {
    id: 'whatsapp-mobile',
    label: 'واتساپ',
    value: '09925339475',
    href: 'https://wa.me/989925339475',
    icon: 'whatsapp',
  },
  {
    id: 'bale',
    label: 'بله',
    value: '@WebinaCo',
    href: 'https://ble.ir/WebinaCo',
    icon: 'bale',
  },
  {
    id: 'eitaa',
    label: 'ایتا',
    value: '09925339475',
    href: 'https://eitaa.com/sms?phone=09925339475',
    icon: 'eitaa',
  },
  {
    id: 'rubika',
    label: 'روبیکا',
    value: '09925339475',
    href: 'https://rubika.ir',
    hint: 'جستجو با شماره',
    icon: 'rubika',
  },
  {
    id: 'gap',
    label: 'گپ',
    value: '09925339475',
    href: 'tel:+989925339475',
    icon: 'gap',
  },
  {
    id: 'igap',
    label: 'آیگپ',
    value: '09925339475',
    href: 'tel:+989925339475',
    icon: 'igap',
  },
  {
    id: 'linkedin',
    label: 'لینکدین',
    value: 'Webina',
    href: 'https://ir.linkedin.com/company/webinadev',
    icon: 'linkedin',
  },
  {
    id: 'website',
    label: 'وب‌سایت',
    value: 'webina.dev',
    href: 'https://webina.dev',
    icon: 'web',
  },
];

function ChannelIcon({ kind }: { kind: Channel['icon'] }) {
  const cls = 'size-5 shrink-0';
  switch (kind) {
    case 'phone':
      return <Phone className={cls} aria-hidden />;
    case 'instagram':
      return <Camera className={cls} aria-hidden />;
    case 'telegram':
      return <Send className={cls} aria-hidden />;
    case 'whatsapp':
    case 'bale':
    case 'eitaa':
    case 'rubika':
    case 'gap':
    case 'igap':
      return <MessageCircle className={cls} aria-hidden />;
    case 'linkedin':
      return <Users className={cls} aria-hidden />;
    case 'web':
      return <Globe className={cls} aria-hidden />;
    default:
      return <AtSign className={cls} aria-hidden />;
  }
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

  return (
    <div className={`connect-hub${ready ? ' is-ready' : ''}`}>
      <div className="connect-hub-glow" aria-hidden />
      <div className="connect-hub-grain" aria-hidden />

      <header className="connect-hub-hero">
        <a href="https://webina.dev" className="connect-hub-brand" aria-label="وبینا">
          <BrandMark className="h-11 w-auto shrink-0" />
          <BrandWordmark className="h-7 w-auto max-w-[9rem]" />
        </a>
        <p className="connect-hub-kicker">ارتباط با وبینا</p>
        <h1 className="connect-hub-title">همهٔ راه‌های تماس، یک‌جا</h1>
        <p className="connect-hub-lead">
          پشتیبانی، شبکه‌های اجتماعی و وب‌سایت رسمی — مستقیم و بدون سردرگمی.
        </p>
      </header>

      <ul className="connect-hub-list">
        {CHANNELS.map((ch, i) => (
          <li key={ch.id} style={{ ['--i' as string]: String(i) }}>
            <a className="connect-hub-link" href={ch.href} target="_blank" rel="noreferrer">
              <span className="connect-hub-icon">
                <ChannelIcon kind={ch.icon} />
              </span>
              <span className="connect-hub-meta">
                <span className="connect-hub-label">{ch.label}</span>
                <span className="connect-hub-value" dir="ltr">
                  {ch.value}
                </span>
                {ch.hint ? <span className="connect-hub-hint">{ch.hint}</span> : null}
              </span>
              <ExternalLink className="connect-hub-ext size-4 opacity-50" aria-hidden />
            </a>
            <button
              type="button"
              className="connect-hub-copy"
              onClick={() => void copyValue(ch.id, ch.value)}
              aria-label={`کپی ${ch.label}`}
            >
              {copied === ch.id ? 'کپی شد' : 'کپی'}
            </button>
          </li>
        ))}
      </ul>

      <footer className="connect-hub-foot">
        <a href="https://webina.dev">بازگشت به webina.dev</a>
      </footer>
    </div>
  );
}
