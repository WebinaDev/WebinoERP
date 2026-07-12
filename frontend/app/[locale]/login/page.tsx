import { LoginForm } from '@/features/shell/login/LoginForm';

export default function LoginPage() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <div className="w-full max-w-3xl">
        <LoginForm />
      </div>
    </div>
  );
}
