'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getMyProfile, updateMyProfile } from '@/lib/api/hrm';

export function MyProfilePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [mobile, setMobile] = useState('');
  const [address, setAddress] = useState('');
  const [iban, setIban] = useState('');
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getMyProfile();
      const profile = res?.profile;
      if (profile) {
        setName(`${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim());
        setEmail(profile.email ?? '');
        const contact = profile.sections?.contact_info?.fields;
        setMobile(contact?.mobile_phone?.value ?? profile.mobile ?? '');
        setAddress(contact?.address?.value ?? profile.address ?? '');
        setIban(profile.sections?.financial_info?.fields?.iban?.value ?? profile.iban ?? '');
      }
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async () => {
    try {
      await updateMyProfile({ mobile_phone: mobile, address, iban });
      setSuccess(t('portal.profileSaved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.myProfile')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-4 max-w-xl text-start">
          <Card>
            <CardContent className="pt-6 space-y-3">
              <div className="text-sm text-muted-foreground">{t('portal.profileReadOnlyHint')}</div>
              <div className="font-medium">{name || '—'}</div>
              <div className="text-sm">{email || '—'}</div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6 grid gap-3">
              <label className="text-sm font-medium">{t('portal.mobile')}</label>
              <Input dir="ltr" value={mobile} onChange={(e) => setMobile(e.target.value)} />
              <label className="text-sm font-medium">{t('portal.address')}</label>
              <Textarea value={address} onChange={(e) => setAddress(e.target.value)} />
              <label className="text-sm font-medium">{t('portal.iban')}</label>
              <Input dir="ltr" value={iban} onChange={(e) => setIban(e.target.value)} />
              <Button onClick={() => void save()}>{t('portal.submitChange')}</Button>
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
