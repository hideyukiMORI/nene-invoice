import { SignInForm } from '@/features/sign-in'

export function LoginPage() {
  return (
    <main className="auth">
      <div className="auth-card">
        <SignInForm />
      </div>
    </main>
  )
}
