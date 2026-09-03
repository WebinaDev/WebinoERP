'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { accountingWpAction } from '@/lib/accounting-wp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';

type Settings = { currency: string; fiscal_year_id: number | null };
type FiscalYear = { id: number; title: string };

export default function AccSettings() {
  const [currency, setCurrency] = useState('');
  const [fyId, setFyId] = useState('');
  const [fys, setFys] = useState<FiscalYear[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [seeding, setSeeding] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const loadSettings = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [settingsRes, fysRes] = await Promise.all([
        apiClient.get('/v1/accounting/settings'),
        apiClient.get('/v1/accounting/fiscal-years', { params: { per_page: 100 } }),
      ]);
      const s = unwrapData<Settings>(settingsRes);
      setCurrency(s.currency ?? '');
      setFyId(s.fiscal_year_id ? String(s.fiscal_year_id) : '');
      setFys(normalizeListPayload(fysRes.data) as unknown as FiscalYear[]);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadSettings();
  }, [loadSettings]);

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    setMessage(null);
    try {
      await accountingWpAction('user_defaults_save', {
        currency,
        fiscal_year_id: fyId ? Number(fyId) : null,
      });
      setMessage('تنظیمات ذخیره شد');
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  };

  const handleSeed = async () => {
    setSeeding(true);
    setError(null);
    setMessage(null);
    try {
      await accountingWpAction('seed_chart');
      setMessage('نمودار حساب‌ها با موفقیت ایجاد شد');
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSeeding(false);
    }
  };

  if (loading) {
    return (
      <div className="space-y-6">
        <h2 className="text-lg font-semibold">تنظیمات حسابداری</h2>
        <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold">تنظیمات حسابداری</h2>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {message && <p className="text-sm text-green-600">{message}</p>}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">تنظیمات پیش‌فرض</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium">واحد پول</label>
            <Input
              value={currency}
              onChange={(e) => setCurrency(e.target.value)}
              placeholder="مثلاً ریال"
              className="max-w-xs"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">سال مالی پیش‌فرض</label>
            <Select value={fyId} onValueChange={setFyId}>
              <SelectTrigger className="max-w-xs">
                <SelectValue placeholder="انتخاب سال مالی" />
              </SelectTrigger>
              <SelectContent>
                {fys.map((f) => (
                  <SelectItem key={f.id} value={String(f.id)}>{f.title}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button onClick={() => void handleSave()} disabled={saving}>
            {saving ? 'در حال ذخیره…' : 'ذخیره تنظیمات'}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">ایجاد نمودار حساب‌ها</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="mb-3 text-sm text-muted-foreground">
            با کلیک بر دکمه زیر، نمودار حساب‌های پیش‌فرض ایجاد خواهد شد.
          </p>
          <Button variant="outline" onClick={() => void handleSeed()} disabled={seeding}>
            {seeding ? 'در حال ایجاد…' : 'ایجاد نمودار حساب‌ها'}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
