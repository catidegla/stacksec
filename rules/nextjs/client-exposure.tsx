import { redirect } from "next/navigation"
import DOMPurify from "isomorphic-dompurify"

export function envUsage() {
  // ruleid: nextjs-public-env-holds-a-secret
  const a = process.env.NEXT_PUBLIC_STRIPE_SECRET_KEY

  // ruleid: nextjs-public-env-holds-a-secret
  const b = process.env.NEXT_PUBLIC_DATABASE_PASSWORD

  // ruleid: nextjs-public-env-holds-a-secret
  const c = process.env.NEXT_PUBLIC_API_TOKEN

  // ruleid: nextjs-public-env-holds-a-secret
  const d = process.env.NEXT_PUBLIC_JWT_PRIVATE_KEY

  // These carry "KEY" or "TOKEN" and are public by design. Flagging them
  // would be wrong, and being wrong about the obvious cases is how a ruleset
  // gets switched off.
  // ok: nextjs-public-env-holds-a-secret
  const e = process.env.NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY

  // ok: nextjs-public-env-holds-a-secret
  const f = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY

  // ok: nextjs-public-env-holds-a-secret
  const g = process.env.NEXT_PUBLIC_RECAPTCHA_SITE_KEY

  // ok: nextjs-public-env-holds-a-secret
  const h = process.env.NEXT_PUBLIC_SENTRY_DSN

  // Server side, no prefix, which is the correct place for a secret.
  // ok: nextjs-public-env-holds-a-secret
  const i = process.env.STRIPE_SECRET_KEY

  // ok: nextjs-public-env-holds-a-secret
  const j = process.env.NEXT_PUBLIC_SITE_URL

  return [a, b, c, d, e, f, g, h, i, j]
}

export function redirects(searchParams: URLSearchParams) {
  // ruleid: nextjs-open-redirect-from-query
  redirect(searchParams.get("next"))
}

export function redirectsWithFallback(searchParams: URLSearchParams) {
  // ruleid: nextjs-open-redirect-from-query
  redirect(searchParams.get("next") ?? "/")
}

export function redirectsSafely(searchParams: URLSearchParams) {
  const next = searchParams.get("next") ?? "/"

  // The double slash test is the part people miss. "//evil.example.com"
  // starts with a slash and still leaves the origin.
  const safe = next.startsWith("/") && !next.startsWith("//") ? next : "/"

  // ok: nextjs-open-redirect-from-query
  redirect(safe)
}

export async function proxies(searchParams: URLSearchParams) {
  // ruleid: nextjs-ssrf-from-query
  const a = await fetch(searchParams.get("url"))

  return a
}

export async function proxiesSafely(searchParams: URLSearchParams) {
  const allowed = ["images.example.com", "cdn.example.com"]
  const raw = searchParams.get("url") ?? ""
  const parsed = new URL(raw)

  if (!allowed.includes(parsed.hostname)) throw new Error("host not allowed")

  // ok: nextjs-ssrf-from-query
  const a = await fetch(parsed.toString())

  // A fixed internal endpoint is not SSRF.
  // ok: nextjs-ssrf-from-query
  const b = await fetch("https://api.example.com/health")

  return [a, b]
}

export function Article({ post }: { post: { body: string } }) {
  // ruleid: nextjs-dangerously-set-inner-html
  return <div dangerouslySetInnerHTML={{ __html: post.body }} />
}

export function SafeArticle({ post }: { post: { body: string } }) {
  // ok: nextjs-dangerously-set-inner-html
  return <div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(post.body) }} />
}

export function StaticMarkup() {
  // Authored, not injected.
  // ok: nextjs-dangerously-set-inner-html
  return <div dangerouslySetInnerHTML={{ __html: "<b>hello</b>" }} />
}
