'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { getStock } from '@/lib/api/scm';

export function StockPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [warehouseId, setWarehouseId] = useState('');
  const [productId, setProductId] = useState('');
  const [result, setResult] = useState<Record<string, unknown> | null>(null);

  const lookup = async () => {
    try {
      const res = await getStock(warehouseId, productId);
      setResult(res as Record<string, unknown>);
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.scm.stock')} {...layoutProps}>
      <Card>
        <CardContent className="flex flex-wrap gap-2 pt-6">
          <Input placeholder={t('warehouse')} value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} />
          <Input placeholder="Product ID" value={productId} onChange={(e) => setProductId(e.target.value)} />
          <Button onClick={() => void lookup()}>{t('stockLookup')}</Button>
        </CardContent>
      </Card>
      {result ? <Card><CardContent className="pt-6"><pre className="text-sm">{JSON.stringify(result, null, 2)}</pre></CardContent></Card> : null}
    </CrmPageLayout>
  );
}
