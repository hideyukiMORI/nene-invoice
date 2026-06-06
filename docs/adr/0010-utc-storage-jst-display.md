# ADR 0010: Store timestamps in UTC, display in JST

## Status

accepted

## Context

Timestamps were generated with ambient `date()` / `new DateTimeImmutable()` and
stored as naive `Y-m-d H:i:s` strings in the server's local timezone (deployments
assumed `Asia/Tokyo`). This has two problems:

- **No canonical timezone.** A deployment whose server clock is not JST would
  silently store and display wrong times. Nothing in the code declared the
  intended zone, so the correctness of 発行日 / 入金日 depended on host config.
- **Time source is implicit and untestable.** Use cases read the wall clock
  directly, so time-dependent logic (issue date, due-date math, dashboard month
  buckets, token expiry, login throttling) could not be exercised deterministically.

The qualified-invoice **交付年月日 (issue date)** is statutory and, once issued,
**immutable** (`docs/explanation/accounting-compliance.md` §2, §5). It must be the
**Japan calendar date** of issuance regardless of where the server runs. We are
pre-launch with no customer data, so this is the moment to fix the model with no
migration risk.

## Decision

1. **Canonical storage is UTC.** The process timezone is forced to UTC at
   bootstrap (`src/bootstrap.php`, wired via Composer `autoload.files`), so every
   ambient `date()` and repository `created_at`/`updated_at`/`issued_at`/`paid_at`
   write produces a UTC instant. The JSON API returns these stored UTC strings
   as-is; **UTC is the documented convention** for all instant fields.

2. **The authoritative clock is the server, via `ClockInterface` (UtcClock).**
   Client-supplied time is never trusted for stored instants. Use cases that need
   "now" depend on the framework `Nene2\Http\ClockInterface`, registered to
   `UtcClock`, so time is injectable and tests pin a fixed instant.

3. **Display is JST.** All user-facing output converts UTC → JST via
   `NeneInvoice\Support\Jst`: PDF (発行日), CSV exports, and the admin frontend.

4. **Calendar-date fields are computed in JST.** `due_at` (支払期限),
   `valid_until` (有効期限), the document-number fiscal year, the "today" used by
   list filters / overdue checks, and dashboard month boundaries are derived from
   the **JST** wall clock (the clock's UTC instant re-zoned to JST), so the
   Japanese calendar day/month/year is correct around the UTC midnight boundary.
   These fields are stored as JST calendar dates and need no display conversion.

## Consequences

- 発行日 / 支払期限 / 有効期限 remain correct Japan dates independent of host
  timezone; the rule is now explicit and enforced in code, not host config.
- Time-dependent use cases are deterministically testable (fixed-instant clock).
- Instant fields in API responses are UTC; the admin frontend converts to JST for
  display (separate change). External read-only consumers (ServiceApi) receive UTC.
- Tax-compliance impact: none to the statutory figures or the displayed issue
  date — only the storage zone and the (previously implicit) derivation rule
  change. No deviation from `docs/explanation/accounting-compliance.md`.

## Related

- Issue: `#342`
- PR: `#350`
- Supersedes: none
- Superseded by: none
