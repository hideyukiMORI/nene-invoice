# MFA — Standalone TOTP (design)

> **Status:** design / proposed — tracking [#524](https://github.com/hideyukiMORI/nene-invoice/issues/524).
> **Conforms to:** NeNe Suite **ADR 0025** (MFA / Step-Up Authentication). A thin
> NeNe Invoice ADR (**0019**) will formalize the decision at implementation kickoff;
> this document is the detailed build spec.
> **Reference implementation:** NeNe Clear (`nene-clear/src/Mfa/`, commits #213 foundation +
> #216 enrolment) — Invoice mirrors it.

## 1. Purpose & posture

Add **two-factor authentication (TOTP)** for operator accounts. MFA was flagged a
**P1 enterprise blocker** in NeNe Clear's adoption review (clear#195) and is a
recurring "trust" lever for self-hosted financial data.

Per Suite ADR 0025, authentication is ultimately **federated** (Suite is the IdP),
but the owner decided MFA must work in **both** deployment modes:

- **Standalone (now):** Invoice's **local login** performs the TOTP challenge — it
  is the only place that sees the password. **This document scopes the standalone path.**
- **Federated (later):** the **Suite IdP enforces** MFA at login; the SSO assertion
  carries a *step-up-satisfied* claim (OIDC `amr`/`acr` style); Invoice trusts the
  assertion and runs **no local MFA**. Invoice's local MFA code stays for
  never-federated installs. This lands with the federation epic (#493–#497).

**MFA is decoupled from the federation roadmap** (ADR 0025 §5): we implement
standalone TOTP now; when Invoice federates, login (and MFA) move to the IdP.

### Non-negotiables (from ADR 0025)

- **No new authentication repository.** The TOTP mechanism is generic; the ideal
  long-term home is a NENE2 primitive (NENE2#1427). Until that exists, Invoice ships
  a **self-contained** RFC 6238 implementation (same as Clear) and can later swap to
  the NENE2 primitive without changing the enrol/challenge/enforce flow.
- **enroll vs enforce.** `enroll` = the user's own device; `enforce` = an admin
  policy. **User-optional ON/OFF is rejected** (optional MFA only protects opt-ins).
  Standalone enforcement policy lives in the app layer (per-deployment). *MVP ships
  self-service enrol; an admin "require MFA" enforcement toggle is a follow-up.*
- **Recovery codes are mandatory at enrolment** (all modes).
- **Break-glass is MFA-exempt, audited.** Standalone = a server/CLI MFA-disable
  command (server-access holder only) + audit.

### Relationship to existing auth posture

- Invoice login stays IP-throttled (`docs` / security posture); **we do NOT add
  password/account lockout.** The lockout introduced here is a **TOTP-code attempt
  lock** (per user, after repeated bad codes) — a distinct control.
- Local session remains the HMAC bearer (`NENE2_LOCAL_JWT_SECRET`, ADR 0014). The MFA
  challenge token is derived from that secret (see §5).

## 2. Architecture overview

```
POST /auth/login (email+password)
      │  password OK
      ├─ TOTP not enabled ─────────────► issue session token (unchanged)
      └─ TOTP enabled ─► issue 5-min MFA challenge token ─► { mfa_required:true, mfa_token, user }
                                                                   │
POST /auth/login/mfa { mfa_token, code }  ──► verify TOTP or recovery code ──► session token

(authenticated, self-service enrolment)
POST   /admin/auth/totp/setup    → { secret, otpauth_uri }            (generate pending secret)
POST   /admin/auth/totp/enable   { code } → { recovery_codes:[10] }   (confirm + activate)
GET    /admin/auth/totp          → { enabled, recovery_codes_remaining }
DELETE /admin/auth/totp          { code } → { disabled:true }         (TOTP or recovery proof)
```

New domain folder `src/Mfa/` (mirrors `nene-clear/src/Mfa/`), wired by a
`MfaServiceProvider` + `MfaRouteRegistrar`, registered in `RuntimeServiceProvider`
and `ApplicationServiceProvider` like every other domain.

## 3. Data model

Three tables. **All three must satisfy schema parity** (Phinx migration **and**
SQLite snapshot in `database/schema/*.sql` **and** installer `database/schema/mysql/schema.sql`;
guarded by `tests/Installer/SchemaParityTest`).

### `totp_secrets` (one row per user)
| column | type | notes |
| --- | --- | --- |
| `user_id` | INT PK | one enrolment per user |
| `secret` | VARCHAR(255) | Base32 seed; **encrypted at rest** (`enc:v1:` prefix) when a key is set |
| `is_enabled` | TINYINT(1) NOT NULL DEFAULT 0 | true once confirmed |
| `failed_attempts` | INT NOT NULL DEFAULT 0 | TOTP-code lock counter (reset on success) |
| `locked_until` | DATETIME NULL | lock expiry; null = unlocked |
| `created_at` | DATETIME NOT NULL | |

### `used_totp_steps` (replay guard)
| column | type | notes |
| --- | --- | --- |
| `id` | INT PK AI | |
| `user_id` | INT NOT NULL | |
| `time_step` | INT NOT NULL | `floor(unixtime/30)` |
| `used_at` | DATETIME NOT NULL | |
| | UNIQUE `(user_id, time_step)` | a consumed step cannot be reused |

### `recovery_codes`
| column | type | notes |
| --- | --- | --- |
| `id` | INT PK AI | |
| `user_id` | INT NOT NULL | |
| `code_hash` | VARCHAR(255) NOT NULL | `password_hash(code, PASSWORD_DEFAULT)` (bcrypt) |
| `used_at` | DATETIME NULL | one-time; set when consumed |
| `created_at` | DATETIME NOT NULL | |
| | INDEX `(user_id)` | |

> Dev SQLite note: after adding columns to an existing dev DB, run the manual ALTER
> (see [[billing-defaults-and-dev-db]] pattern) or recreate; Docker MySQL re-migrates.

## 4. HTTP API

### Enrolment (authenticated; under `/admin/`, bearer-gated by `BearerTokenMiddleware`)

`CapabilityResolver` returns **null** for `/admin/auth/totp*` (no billing
capability — it's the caller's own MFA, like `GET /admin/me`). Any authenticated
operator can manage their own TOTP.

| operationId | method · path | request | 200 response |
| --- | --- | --- | --- |
| `setupTotp` | POST `/admin/auth/totp/setup` | — | `{ secret, otpauth_uri }` |
| `enableTotp` | POST `/admin/auth/totp/enable` | `{ code }` | `{ recovery_codes: [10 strings] }` |
| `getTotpStatus` | GET `/admin/auth/totp` | — | `{ enabled: bool, recovery_codes_remaining: int }` |
| `disableTotp` | DELETE `/admin/auth/totp` | `{ code }` (TOTP **or** recovery) | `{ disabled: true }` |

- `setup` (re)generates a **pending** secret and clears any prior used-steps +
  recovery codes (idempotent re-enrol).
- `enable` confirms the pending secret with a code, flips `is_enabled`, issues the
  10 recovery codes (**returned once, never again**), audits `mfa_enabled`.
- `disable` requires proof (recovery code tried first to avoid burning the TOTP lock
  if the device is lost), deletes secret + used-steps + recovery codes, audits `mfa_disabled`.

### Login step-up (public)

| operationId | method · path | request | response |
| --- | --- | --- | --- |
| `login` (existing, modified) | POST `/auth/login` | `{ email, password }` | TOTP off → `{ token, user }` (unchanged) · TOTP on → `{ mfa_required: true, mfa_token, user }` (no `token`) |
| `verifyMfaLogin` | POST `/auth/login/mfa` | `{ mfa_token, code }` | `{ token, user }` |

**OpenAPI:** add all five operations + schemas to `docs/openapi/openapi.yaml`,
register the operationIds in `tests/OpenApi/OpenApiContractTest` and terminology §5,
declare any path params. `OpenApiContractTest` requires the implemented-endpoint set
to match the spec exactly.

## 5. Cryptography

- **TOTP:** RFC 6238, HMAC-SHA1, 6 digits, 30 s period, **±1 step** verification
  window (60 s skew tolerance). Self-contained (no external library); Base32 alphabet
  `A–Z2–7`; secret = 20 random bytes → Base32. `otpauth://totp/NeNe%20Invoice:{email}?secret=…&issuer=NeNe%20Invoice&algorithm=SHA1&digits=6&period=30`.
- **Secret at rest:** Invoice has **no Encryptor yet** — add `src/Security/Encryptor.php`
  (libsodium `sodium_crypto_secretbox`, 24-byte nonce, `enc:v1:base64(nonce‖ct)`),
  keyed by **`NENE_INVOICE_ENCRYPTION_KEY`** (base64 32 bytes). **Passthrough when
  unset** (stores plaintext, reads back as-is) so dev/standalone installs work without
  key management; production should set a key. Decrypt branches on the `enc:v1:` prefix
  (lazy migration / rotation-safe).
- **Recovery codes:** format `{5-hex}-{5-hex}`, 10 per user, stored
  `password_hash(…, PASSWORD_DEFAULT)`, verified with `password_verify`, one-time.
- **MFA challenge token:** short-lived (5 min) HS256 JWT, claim `mfa=pending` + `sub`,
  signed with a **derived** key `HMAC-SHA256('nene-invoice:mfa-challenge:v1', NENE2_LOCAL_JWT_SECRET)`
  (domain-separated from the session secret). Verified only by `/auth/login/mfa`.

## 6. Security controls

- **Replay protection:** `used_totp_steps` unique `(user_id, time_step)`; a matched
  code whose step is already recorded is rejected.
- **TOTP-code lockout:** 3 consecutive bad codes → 15-min lock (`locked_until`);
  counter resets on success; lock checked **before** verification (no timing oracle).
  `hash_equals` for code comparison.
- **Recovery codes mandatory** at enrol; tried before TOTP at login/disable.
- **Break-glass:** CLI `php tools/disable-user-mfa.php --user=<id>` (server-access only),
  deletes the user's TOTP rows + audits. (Federated break-glass = ADR 0012 §6, later.)

## 7. Exceptions → Problem Details

Base `https://nene-invoice.dev/problems/`. New domain exceptions + handlers
(mirroring the Quote/Client exception-handler pattern, registered in `ApplicationServiceProvider`):

| exception | slug | status |
| --- | --- | --- |
| `TotpInvalidCodeException` | `totp-invalid-code` | 401 |
| `TotpLockedException` (carries `locked_until`) | `totp-locked` | 423 |
| `TotpNotEnabledException` | `totp-not-enabled` | 409 |
| `TotpAlreadyEnabledException` | `totp-already-enabled` | 409 |
| `MfaChallengeInvalidException` | `mfa-challenge-invalid` | 401 |

## 8. Terminology registry additions (`docs/explanation/terminology.md`, binding)

- **Entities (§1):** `TotpSecret` / `totp_secrets`; `UsedTotpStep` / `used_totp_steps`;
  `RecoveryCode` / `recovery_codes`.
- **Fields (§3):** `secret`, `is_enabled`, `failed_attempts`, `locked_until`,
  `time_step`, `code_hash`, `used_at`; login `mfa_required`, `mfa_token`, `code`,
  `recovery_codes`, `recovery_codes_remaining`, `otpauth_uri`.
- **Problem slugs (§4):** the five above.
- **operationIds (§5):** `setupTotp`, `enableTotp`, `getTotpStatus`, `disableTotp`, `verifyMfaLogin`.
- **Audit actions:** `mfa_enabled`, `mfa_disabled` (entity `user`, before/after per ADR 0008).

## 9. Frontend (Invoice ships UI; Clear has not yet)

- **Login (2-step):** `/auth/login` may return `{ mfa_required, mfa_token }` instead of
  a token — the sign-in flow shows a code input and calls `/auth/login/mfa`. Touches the
  in-memory token + fail-closed `AuthGate` flow ([[frontend-auth-session]]); the token is
  only set after step-up succeeds.
- **Settings → security:** an MFA card showing status; "二要素認証を設定" → `setup`
  renders a **QR (from `otpauth_uri`)** + manual key → confirm 6-digit → **show 10 recovery
  codes once** (copy/print) → enabled. Disable with confirmation (ConfirmDialog) + code.
- New shared dependency: a QR renderer (e.g. `qrcode` / `qrcode.react`) — the client
  draws the QR from the `otpauth_uri` (no secret math on the client). Error display uses
  the 型1 field / 型2 InlineAlert system ([[error-display-3type-system]]); i18n ja/en.

## 10. Configuration

- `.env.example`: add `NENE_INVOICE_ENCRYPTION_KEY=` (base64 32 bytes; empty = passthrough).
- Docker `compose.yaml` app env: pass it through (literal/empty default), mirroring the
  existing env wiring.

## 11. Testing

- **Unit:** TOTP RFC 6238 reference vectors; Base32; authenticator (lock timing,
  replay, recovery-first); RecoveryCodeService; Encryptor round-trip + passthrough.
- **Use case:** setup/enable/disable (audit recorded; recovery codes issued once).
- **HTTP:** enrol flow (setup→enable→status→disable, error slugs), login step-up
  (password → mfa_required → verify → token; bad/locked/replayed code).
- **Parity:** `SchemaParityTest` green (3 tables in installer schema).
- Date-sensitive lock/step tests use `FixedClock` ([[fixedclock-test-time-dependence]]).
- `composer check` + `npm run check` green.

## 12. Phased plan (→ increments / PRs)

1. **ADR 0019** (proposed→accepted): conform to Suite ADR 0025, standalone TOTP.
2. **Backend foundation:** `Encryptor`; `TotpGenerator`/`TotpSecret`/`RecoveryCodeService`/
   `TotpAuthenticator`; 3 tables (migration + SQLite + installer schema parity); repositories.
3. **Enrol API:** use cases + handlers + route registrar + DI + exceptions/handlers +
   OpenAPI + terminology + tests.
4. **Login step-up:** modify `LoginUseCase`; `MfaChallengeTokens`; `VerifyMfaLogin` use
   case + handler + route; audit.
5. **Frontend:** settings MFA card (QR + recovery codes) + 2-step login + i18n + tests.
6. **Break-glass CLI** + ops note.
7. *(later, federated)* read Suite step-up assertion claim in `AssertionTokenVerifier`;
   retire local MFA when federated (keep for standalone).

## 13. Invoice-specific deltas vs Clear (don't copy blindly)

- Namespace `NeneInvoice\`; challenge HMAC namespace `nene-invoice:mfa-challenge:v1`.
- **Invoice has no Encryptor** → add one (Clear got it via bank-account encryption #207);
  do **not** wire it to unrelated domain fields.
- **Schema parity** is an Invoice hard gate (installer `mysql/schema.sql` + `SchemaParityTest`)
  — Clear's installer model differs.
- **OpenAPI contract test** is an Invoice hard gate — every endpoint must be in the spec.
- Endpoint prefixes match Invoice: enrol under `/admin/auth/totp*` (bearer-gated),
  login at `/auth/login` + `/auth/login/mfa` (public) — Invoice login is `/auth/login`
  (not `/admin/auth/login`).
- Invoice's React app is fuller than Clear's, so Invoice ships the enrol UI now.
- Compliance: this is an **authentication** change, not accounting — **no 税理士 gate**
  (`accounting-compliance.md` unaffected).

## 14. Open decisions (confirm at kickoff)

1. **Enforcement in MVP?** Ship self-service enrol only, or also an admin "require MFA
   for this org" policy now? (ADR 0025 allows app-layer enforcement; recommend enrol-first,
   enforce as a fast follow.)
2. **Encryption mandatory or passthrough-default?** Recommend passthrough default (dev
   friendliness) with a strong "set a key in production" note + installer prompt later.
3. **Recovery-code count** (10, per Clear) and **lock policy** (3/15 min, per Clear) —
   adopt Clear's values for portfolio consistency unless a reason to differ.
4. **NENE2 primitive:** copy Clear's self-contained TOTP now; migrate to NENE2#1427 when it lands.

## 15. References

- Suite **ADR 0025** — `../nene-suite/docs/adr/0025-mfa-step-up-authentication.md`
- Suite **ADR 0012** — Federation Participation Contract (federated MFA path)
- NeNe Clear reference impl — `../nene-clear/src/Mfa/` (commits #213, #216)
- Invoice tracking Issue — #524
