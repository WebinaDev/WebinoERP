'use client';

import { useTranslations } from 'next-intl';
import { Briefcase, IdCard, MapPin, PhoneCall } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { UserProfile } from '@/lib/api/users';

type Key = keyof UserProfile;

const SECTIONS: Array<{ key: string; icon: React.ReactNode; fields: Array<{ name: Key; ltr?: boolean; wide?: boolean; area?: boolean }> }> = [
  { key: 'sectionIdentity', icon: <IdCard className="size-4" />, fields: [{ name: 'national_id', ltr: true }, { name: 'birth_date' }, { name: 'gender' }, { name: 'father_name' }] },
  { key: 'sectionContact', icon: <MapPin className="size-4" />, fields: [{ name: 'phone_alt', ltr: true }, { name: 'landline', ltr: true }, { name: 'province' }, { name: 'city' }, { name: 'postal_code', ltr: true }, { name: 'address', wide: true, area: true }] },
  { key: 'sectionWork', icon: <Briefcase className="size-4" />, fields: [{ name: 'job_title' }, { name: 'company' }, { name: 'bio', wide: true, area: true }] },
  { key: 'sectionEmergency', icon: <PhoneCall className="size-4" />, fields: [{ name: 'emergency_contact_name' }, { name: 'emergency_contact_phone', ltr: true }] },
];

/** Shared personal-details fields; admins also get the private admin notes field. */
export function ProfileFieldsForm({ value, onChange, admin = false }: { value: UserProfile; onChange: (v: UserProfile) => void; admin?: boolean }) {
  const t = useTranslations('userAdmin');
  const set = (k: Key, v: string) => onChange({ ...value, [k]: v === '' ? null : v });

  return (
    <div className="grid gap-4 xl:grid-cols-2">
      {SECTIONS.map((s) => (
        <Card key={s.key} className="shadow-sm">
          <CardContent className="space-y-3 p-4">
            <h3 className="flex items-center gap-2 text-sm font-semibold"><span className="text-primary">{s.icon}</span>{t(`profile.${s.key}`)}</h3>
            <div className="grid gap-3 sm:grid-cols-2">
              {s.fields.map((f) => (
                <label key={f.name} className={`grid gap-1 text-sm ${f.wide ? 'sm:col-span-2' : ''}`}>
                  {t(`profile.${f.name}`)}
                  {f.name === 'birth_date' ? (
                    <LocaleDatePicker value={value.birth_date ?? null} onChange={(v) => set('birth_date', v)} />
                  ) : f.name === 'gender' ? (
                    <Select value={value.gender ?? ''} onValueChange={(v) => set('gender', v)}>
                      <SelectTrigger><SelectValue placeholder="-" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="male">{t('profile.genderMale')}</SelectItem>
                        <SelectItem value="female">{t('profile.genderFemale')}</SelectItem>
                        <SelectItem value="other">{t('profile.genderOther')}</SelectItem>
                      </SelectContent>
                    </Select>
                  ) : f.area ? (
                    <Textarea rows={2} value={(value[f.name] as string | null) ?? ''} onChange={(e) => set(f.name, e.target.value)} />
                  ) : (
                    <Input dir={f.ltr ? 'ltr' : undefined} value={(value[f.name] as string | null) ?? ''} onChange={(e) => set(f.name, e.target.value)} />
                  )}
                </label>
              ))}
            </div>
          </CardContent>
        </Card>
      ))}
      {admin ? (
        <Card className="shadow-sm xl:col-span-2">
          <CardContent className="p-4">
            <label className="grid gap-1 text-sm">
              {t('profile.admin_notes')}
              <Textarea rows={3} value={value.admin_notes ?? ''} onChange={(e) => set('admin_notes', e.target.value)} />
            </label>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
