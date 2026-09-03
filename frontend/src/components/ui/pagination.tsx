'use client';

import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

export type PaginationProps = {
  page: number;
  pageCount: number;
  total?: number;
  onPageChange: (p: number) => void;
  className?: string;
};

export function Pagination({ page, pageCount, total, onPageChange, className }: PaginationProps) {
  const canPrev = page > 1;
  const canNext = page < pageCount;

  return (
    <div className={cn('flex flex-wrap items-center justify-between gap-2 text-sm', className)}>
      <p className="text-muted-foreground">
        {total != null ? (
          <>
            نمایش صفحه {page} از {pageCount} — {total} مورد
          </>
        ) : (
          <>
            صفحه {page} / {pageCount}
          </>
        )}
      </p>
      <div className="flex gap-1">
        <Button type="button" variant="outline" size="sm" disabled={!canPrev} onClick={() => onPageChange(page - 1)} aria-label="قبلی">
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" disabled={!canNext} onClick={() => onPageChange(page + 1)} aria-label="بعدی">
          <ChevronLeft className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
