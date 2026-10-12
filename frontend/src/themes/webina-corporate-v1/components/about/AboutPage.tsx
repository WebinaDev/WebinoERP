import Link from 'next/link';
import { ArrowUpLeft, Check, Quote, X } from 'lucide-react';
import { siteHref } from '@/lib/public-api-server';
import { toPersianDigits } from '@/lib/locale/calendar-date';
import { cn } from '@/lib/utils';
import type { AboutSection } from '../../about-content';
import { isExternalHref, type ButtonItem, type CompanyData } from '../../site-content';
import { LogoLockup } from '../LogoLockup';
import { NavIcon } from '../NavIcon';

function go(path: string) {
  return isExternalHref(path) ? path : siteHref(undefined, path.replace(/^\//, ''));
}

function Buttons({ items, dark }: { items: ButtonItem[]; dark?: boolean }) {
  return (
    <>
      {items
        .filter((b) => b.label && b.href)
        .map((b, i) => {
          const cls = b.style === 'link' ? 'text-link' : b.style === 'ghost' ? (dark ? 'btn-ghost-dark' : 'btn-ghost') : 'btn-primary';
          return (
            <Link key={i} href={go(b.href)} className={cls}>
              {b.label}
              {b.style === 'primary' ? <ArrowUpLeft className="size-4 ltr:-scale-x-100" aria-hidden /> : null}
            </Link>
          );
        })}
    </>
  );
}

function Lines({ text }: { text: string }) {
  return (
    <>
      {text.split('\n').map((line, i) => (
        <span key={i} className="block">
          {line}
        </span>
      ))}
    </>
  );
}

export function AboutPage({
  sections,
  company,
  siteName,
  logoUrl,
  fa,
  othersLabel,
  webinaLabel,
}: {
  sections: AboutSection[];
  company: CompanyData | null;
  siteName: string;
  logoUrl?: string | null;
  fa: boolean;
  othersLabel: string;
  webinaLabel: string;
}) {
  const num = (n: number) => (fa ? toPersianDigits(String(n).padStart(2, '0')) : String(n).padStart(2, '0'));

  return (
    <div className="about-page">
      {sections.map((section) => {
        switch (section.type) {
          case 'hero': {
            const p = section.props;
            return (
              <header key="hero" className="ab-hero">
                <div className="ab-hero-texture" aria-hidden />
                <div className="webina-shell ab-hero-grid">
                  <div>
                    {p.kicker ? <p className="ab-kicker">{p.kicker}</p> : null}
                    <h1>{p.title}</h1>
                    <span className="ab-underline" aria-hidden />
                    {p.text ? <p className="ab-hero-text">{p.text}</p> : null}
                    <div className="mt-9 flex flex-wrap gap-3">
                      <Buttons items={p.buttons} dark />
                    </div>
                  </div>
                  <div className="ab-hero-art" aria-hidden>
                    <svg viewBox="0 0 420 340" className="w-full">
                      <rect x="20" y="20" width="380" height="300" rx="28" fill="rgba(255,255,255,.04)" stroke="rgba(255,255,255,.12)" />
                      <path d="M60 250 C 120 240 150 180 210 182 S 300 120 360 96" stroke="#4d8bff" strokeWidth="4" fill="none" strokeLinecap="round" />
                      {[60, 150, 240, 330].map((x, i) => (
                        <g key={x}>
                          <circle cx={x + 10} cy={[250, 205, 160, 104][i]} r="9" fill={i === 3 ? '#0054ff' : '#22242a'} stroke="#4d8bff" strokeWidth="3" />
                        </g>
                      ))}
                      <rect x="60" y="56" width="140" height="12" rx="6" fill="rgba(255,255,255,.18)" />
                      <rect x="60" y="80" width="90" height="10" rx="5" fill="rgba(255,255,255,.1)" />
                    </svg>
                    {p.tags.map((tag, i) => (
                      <span key={i} className={cn('ab-tag', `t-${i + 1}`)}>
                        {tag.text}
                      </span>
                    ))}
                  </div>
                </div>
              </header>
            );
          }
          case 'intro': {
            const p = section.props;
            const facts = [...p.facts];
            if (company) {
              const extra = [
                company.legal_type ? { label: fa ? 'نوع شرکت' : 'Type', value: company.legal_type, icon: 'Scale' } : null,
                company.registration_no ? { label: fa ? 'شماره ثبت' : 'Reg. no.', value: company.registration_no, icon: 'FileText' } : null,
                company.national_id ? { label: fa ? 'شناسه ملی' : 'National ID', value: company.national_id, icon: 'Fingerprint' } : null,
                company.registered_at ? { label: fa ? 'تاریخ ثبت' : 'Registered', value: [company.registered_at, company.city].filter(Boolean).join('، '), icon: 'CalendarDays' } : null,
                company.activity ? { label: fa ? 'موضوع فعالیت' : 'Activity', value: company.activity, icon: 'BadgeCheck' } : null,
              ].filter(Boolean) as typeof facts;
              for (const e of extra) if (!facts.some((f) => f.label === e.label)) facts.push(e);
            }
            return (
              <section key="intro" className="section">
                <div className="webina-shell ab-intro">
                  <div className="ab-intro-brand">
                    <LogoLockup siteName={siteName} logoUrl={logoUrl} />
                    {p.logo_caption ? <p>{p.logo_caption}</p> : null}
                  </div>
                  <div>
                    <h2 className="ab-h2">{p.title}</h2>
                    {p.lead ? <p className="ab-lead">{p.lead}</p> : null}
                    <dl className="ab-facts">
                      {facts.map((f, i) => (
                        <div key={i}>
                          <span className="ab-ico">
                            <NavIcon name={f.icon || 'Info'} className="size-4" />
                          </span>
                          <dt>{f.label}</dt>
                          <dd>{f.value}</dd>
                        </div>
                      ))}
                    </dl>
                  </div>
                </div>
              </section>
            );
          }
          case 'story': {
            const p = section.props;
            const paras = p.paragraphs.filter((x) => x.text);
            const mid = Math.ceil(paras.length / 2);
            return (
              <section key="story" className="section ab-story">
                <div className="webina-shell ab-narrow">
                  <h2 className="ab-h2">{p.title}</h2>
                  <div className="ab-story-body">
                    {paras.slice(0, mid).map((x, i) => (
                      <p key={i} className={cn(i === 1 && 'ab-emph')}>
                        <Lines text={x.text} />
                      </p>
                    ))}
                    {p.quote ? (
                      <blockquote className="ab-quote">
                        <Quote className="size-7" aria-hidden />
                        {p.quote}
                      </blockquote>
                    ) : null}
                    {paras.slice(mid).map((x, i) => (
                      <p key={i}>
                        <Lines text={x.text} />
                      </p>
                    ))}
                  </div>
                  {p.path.length ? (
                    <ol className="ab-path" aria-label={p.title}>
                      {p.path.map((step, i) => (
                        <li key={i}>
                          <span>{step.text}</span>
                        </li>
                      ))}
                    </ol>
                  ) : null}
                </div>
              </section>
            );
          }
          case 'mission': {
            const p = section.props;
            return (
              <section key="mission" className="section">
                <div className="webina-shell">
                  <h2 className="ab-h2">{p.title}</h2>
                  <div className="ab-mission">
                    {p.items.map((item, i) => (
                      <article key={i} className={cn('ab-mission-card', i % 2 === 1 && 'is-dark')}>
                        <span className="ab-ico lg">
                          <NavIcon name={item.icon || 'Target'} className="size-6" />
                        </span>
                        <h3>{item.title}</h3>
                        <p>
                          <Lines text={item.text} />
                        </p>
                      </article>
                    ))}
                  </div>
                </div>
              </section>
            );
          }
          case 'values': {
            const p = section.props;
            return (
              <section key="values" className="section ab-values-wrap">
                <div className="webina-shell">
                  <h2 className="ab-h2">{p.title}</h2>
                  <div className="ab-values">
                    {p.items.map((item, i) => (
                      <article key={i} className="ab-value">
                        <span className="ab-ico">
                          <NavIcon name={item.icon || 'Sparkles'} className="size-5" />
                        </span>
                        <h3>{item.title}</h3>
                        {item.text ? <p>{item.text}</p> : null}
                      </article>
                    ))}
                  </div>
                </div>
              </section>
            );
          }
          case 'approach': {
            const p = section.props;
            return (
              <section key="approach" className="section">
                <div className="webina-shell">
                  <h2 className="ab-h2">{p.title}</h2>
                  {p.lead ? (
                    <p className="ab-lead">
                      <Lines text={p.lead} />
                    </p>
                  ) : null}
                  <ol className="ab-steps">
                    {p.steps.map((step, i) => (
                      <li key={i}>
                        <span className="ab-step-no">{num(i + 1)}</span>
                        <h3>{step.title}</h3>
                        {step.text ? <p>{step.text}</p> : null}
                      </li>
                    ))}
                  </ol>
                  {p.outro ? <p className="ab-outro">{p.outro}</p> : null}
                </div>
              </section>
            );
          }
          case 'team': {
            const p = section.props;
            const members = p.members.filter((m) => m.name);
            return (
              <section key="team" className="section">
                <div className="webina-shell">
                  <h2 className="ab-h2">{p.title}</h2>
                  {p.text ? <p className="ab-lead">{p.text}</p> : null}
                  <div className="ab-roles">
                    {p.roles.map((role, i) => (
                      <article key={i} className="ab-role">
                        <span className="ab-ico lg">
                          <NavIcon name={role.icon || 'User'} className="size-7" />
                        </span>
                        <h3>{role.title}</h3>
                      </article>
                    ))}
                  </div>
                  {members.length ? (
                    <div className="ab-members">
                      {members.map((m, i) => (
                        <article key={i} className="ab-member">
                          {m.photo ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img src={m.photo} alt={m.name} loading="lazy" />
                          ) : (
                            <span className="ab-avatar" aria-hidden>
                              {m.name.slice(0, 1)}
                            </span>
                          )}
                          <h3>{m.name}</h3>
                          {m.role ? <p className="ab-member-role">{m.role}</p> : null}
                          {m.quote ? <p className="ab-member-quote">{m.quote}</p> : null}
                        </article>
                      ))}
                    </div>
                  ) : null}
                  {p.culture.length ? (
                    <div className="ab-culture">
                      <h3>{p.culture_title}</h3>
                      <ul>
                        {p.culture.map((c, i) => (
                          <li key={i}>{c.text}</li>
                        ))}
                      </ul>
                    </div>
                  ) : null}
                </div>
              </section>
            );
          }
          case 'different': {
            const p = section.props;
            return (
              <section key="different" className="section ab-diff-wrap">
                <div className="webina-shell">
                  <h2 className="ab-h2">{p.title}</h2>
                  <div className="ab-diff">
                    <div className="ab-diff-col is-others" aria-hidden>
                      <p className="ab-diff-head">{othersLabel}</p>
                      {p.items.map((_, i) => (
                        <span key={i} className="ab-diff-row">
                          <X className="size-4" />
                          <i />
                        </span>
                      ))}
                    </div>
                    <ul className="ab-diff-col is-webina">
                      <li className="ab-diff-head">{webinaLabel}</li>
                      {p.items.map((item, i) => (
                        <li key={i} className="ab-diff-row">
                          <Check className="size-4" aria-hidden />
                          {item.text}
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>
              </section>
            );
          }
          case 'cta': {
            const p = section.props;
            return (
              <section key="cta" className="ab-cta">
                <div className="webina-shell ab-narrow text-center">
                  <h2>{p.title}</h2>
                  {p.text ? <p>{p.text}</p> : null}
                  <div className="mt-9 flex flex-wrap justify-center gap-3">
                    <Buttons items={p.buttons} dark />
                  </div>
                </div>
              </section>
            );
          }
          default:
            return null;
        }
      })}
    </div>
  );
}
