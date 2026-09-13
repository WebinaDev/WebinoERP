'use client';

import { useFormatter, useTranslations } from 'next-intl';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { edgeField, type EdgeRow } from '@/lib/api/modirpayamak-edge';
import { ModirPayamakStatusBadge } from './status-badge';

type Props = {
  items: EdgeRow[];
  onRowClick?: (row: EdgeRow) => void;
};

export function ModirPayamakOutboxTable({ items, onRowClick }: Props) {
  const t = useTranslations('modirpayamak');
  const format = useFormatter();

  const formatDate = (row: EdgeRow) => {
    const raw = edgeField(row, 'created_at', 'sent_at', 'date', 'time');
    if (raw === '—') return raw;
    try {
      return format.dateTime(new Date(raw), { dateStyle: 'short', timeStyle: 'short' });
    } catch {
      return raw.slice(0, 19);
    }
  };

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t('colRecipient')}</TableHead>
          <TableHead>{t('message')}</TableHead>
          <TableHead>{t('status')}</TableHead>
          <TableHead>{t('colDate')}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.map((row, i) => {
          const id = edgeField(row, 'id', 'messages_outbox_id', 'outbox_id');
          const recipient = edgeField(row, 'recipient', 'to', 'mobile', 'phone', 'number');
          const message = edgeField(row, 'message', 'text', 'body', 'content');
          const status = edgeField(row, 'status', 'state', 'delivery_status');
          return (
            <TableRow
              key={id !== '—' ? id : i}
              className={onRowClick ? 'cursor-pointer hover:bg-muted/50' : undefined}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
            >
              <TableCell dir="ltr" className="font-mono text-sm">
                {recipient}
              </TableCell>
              <TableCell className="max-w-xs truncate" title={message}>
                {message}
              </TableCell>
              <TableCell>
                <ModirPayamakStatusBadge status={status} />
              </TableCell>
              <TableCell className="text-sm text-muted-foreground">{formatDate(row)}</TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
