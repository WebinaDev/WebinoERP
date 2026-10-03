import type { ReactNode } from 'react';

export function SitePage({
  kicker,
  title,
  lead,
  children,
}: {
  kicker?: string;
  title: string;
  lead?: string;
  children?: ReactNode;
}) {
  return (
    <div>
      <header className="page-hero">
        <div className="webina-shell">
          {kicker ? <p className="webina-kicker">{kicker}</p> : null}
          <h1 className={kicker ? 'mt-4' : ''}>{title}</h1>
          {lead ? <p className="lead">{lead}</p> : null}
        </div>
      </header>
      {children ? <div className="webina-shell page-body">{children}</div> : <div className="webina-shell page-body" />}
    </div>
  );
}

export function EmptyNote({ children }: { children: ReactNode }) {
  return <p className="empty-note">{children}</p>;
}
