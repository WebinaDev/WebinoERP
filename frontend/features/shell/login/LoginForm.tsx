'use client';

import type { ComponentProps } from 'react';
import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { login, registerUser, sendLoginOtp, verifyLoginOtp } from '@/lib/auth';
import { getAxiosMessage } from '@/lib/api-helpers';
import { toast } from 'sonner';

export function LoginForm({ className, ...props }: ComponentProps<'div'>) {
  const t = useTranslations();
  const params = useParams();
  const router = useRouter();
  const locale = (params?.locale as string) || 'fa';

  const [tab, setTab] = useState<'password' | 'otp' | 'register'>('password');
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(true);
  const [mobile, setMobile] = useState('');
  const [otpCode, setOtpCode] = useState('');
  const [regName, setRegName] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPassword, setRegPassword] = useState('');
  const [regPassword2, setRegPassword2] = useState('');
  const [pending, setPending] = useState(false);

  function goDashboard() {
    router.push('/dashboard');
    router.refresh();
  }

  async function onPasswordSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    try {
      await login(identifier.trim(), password);
      goDashboard();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setPending(false);
    }
  }

  async function onSendOtp(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    try {
      const r = await sendLoginOtp(mobile);
      toast.success(r.message ?? t('login.otpSent'));
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setPending(false);
    }
  }

  async function onVerifyOtp(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    try {
      await verifyLoginOtp(mobile, otpCode);
      goDashboard();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setPending(false);
    }
  }

  async function onRegister(e: React.FormEvent) {
    e.preventDefault();
    if (regPassword !== regPassword2) {
      toast.error(t('login.passwordMismatch'));
      return;
    }
    setPending(true);
    try {
      await registerUser({
        name: regName,
        email: regEmail,
        password: regPassword,
        password_confirmation: regPassword2,
      });
      toast.success(t('login.registerSuccess'));
      setTab('password');
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className={cn('flex flex-col gap-6', className)} {...props}>
      <Card className="overflow-hidden shadow-sm">
        <CardContent className="grid p-0 md:grid-cols-2">
          <div className="flex flex-col gap-6 p-6 md:p-8">
            <div className="flex flex-col items-center gap-2 text-center">
              <h1 className="text-2xl font-bold">{t('login.title')}</h1>
              <p className="text-balance text-sm text-muted-foreground">{t('login.subtitle')}</p>
            </div>

            <Tabs value={tab} onValueChange={(v) => setTab(v as typeof tab)}>
              <TabsList className="grid w-full grid-cols-3">
                <TabsTrigger value="password">{t('login.passwordTab')}</TabsTrigger>
                <TabsTrigger value="otp">OTP</TabsTrigger>
                <TabsTrigger value="register">{t('login.registerTab')}</TabsTrigger>
              </TabsList>

              <TabsContent value="password">
                <form className="mt-4 flex flex-col gap-4" onSubmit={(e) => void onPasswordSubmit(e)}>
                  <div className="grid gap-2">
                    <Label htmlFor="dashboard-login-id">{t('login.identifier')}</Label>
                    <Input
                      id="dashboard-login-id"
                      type="text"
                      autoComplete="username"
                      value={identifier}
                      onChange={(e) => setIdentifier(e.target.value)}
                      required
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="dashboard-login-password">{t('login.password')}</Label>
                    <Input
                      id="dashboard-login-password"
                      type="password"
                      autoComplete="current-password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                    />
                  </div>
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id="dashboard-login-remember"
                      checked={remember}
                      onCheckedChange={(v) => setRemember(v === true)}
                    />
                    <Label htmlFor="dashboard-login-remember" className="cursor-pointer font-normal">
                      {t('login.remember')}
                    </Label>
                  </div>
                  <Button type="submit" className="w-full" disabled={pending}>
                    {pending ? t('login.pending') : t('login.submit')}
                  </Button>
                </form>
              </TabsContent>

              <TabsContent value="otp">
                <form className="mt-4 flex flex-col gap-3" onSubmit={(e) => void onSendOtp(e)}>
                  <div className="grid gap-2">
                    <Label>{t('login.mobile')}</Label>
                    <Input type="tel" value={mobile} onChange={(e) => setMobile(e.target.value)} required />
                  </div>
                  <Button type="submit" variant="secondary" className="w-full" disabled={pending}>
                    {t('login.sendOtp')}
                  </Button>
                </form>
                <form className="mt-4 flex flex-col gap-3 border-t pt-4" onSubmit={(e) => void onVerifyOtp(e)}>
                  <div className="grid gap-2">
                    <Label>OTP</Label>
                    <Input value={otpCode} onChange={(e) => setOtpCode(e.target.value)} />
                  </div>
                  <Button type="submit" className="w-full" disabled={pending}>
                    {t('login.verifyOtp')}
                  </Button>
                </form>
              </TabsContent>

              <TabsContent value="register">
                <form className="mt-4 flex flex-col gap-3" onSubmit={(e) => void onRegister(e)}>
                  <div className="grid gap-2">
                    <Label>{t('login.name')}</Label>
                    <Input value={regName} onChange={(e) => setRegName(e.target.value)} required />
                  </div>
                  <div className="grid gap-2">
                    <Label>Email</Label>
                    <Input type="email" value={regEmail} onChange={(e) => setRegEmail(e.target.value)} required />
                  </div>
                  <div className="grid gap-2">
                    <Label>{t('login.password')}</Label>
                    <Input type="password" value={regPassword} onChange={(e) => setRegPassword(e.target.value)} required />
                  </div>
                  <div className="grid gap-2">
                    <Label>{t('login.confirmPassword')}</Label>
                    <Input type="password" value={regPassword2} onChange={(e) => setRegPassword2(e.target.value)} required />
                  </div>
                  <Button type="submit" className="w-full" disabled={pending}>
                    {t('login.register')}
                  </Button>
                </form>
              </TabsContent>
            </Tabs>
          </div>
          <div className="relative hidden flex-col justify-between gap-4 bg-muted p-8 md:flex">
            <div>
              <p className="text-sm leading-relaxed text-muted-foreground">{t('login.heroHint')}</p>
              <p className="mt-4 font-semibold">{t('app.title')}</p>
            </div>
            <div className="mx-auto flex size-24 items-center justify-center rounded-lg bg-background/80 text-2xl font-semibold shadow-sm">
              W
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
