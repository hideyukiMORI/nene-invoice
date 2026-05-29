import { SignInForm } from '@/features/sign-in'

export function LoginPage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-md items-center px-inline-md py-stack-xl">
      <div className="w-full rounded-md border border-border bg-surface-raised px-inline-lg py-stack-lg shadow-sm">
        <SignInForm />
      </div>
    </main>
  )
}
