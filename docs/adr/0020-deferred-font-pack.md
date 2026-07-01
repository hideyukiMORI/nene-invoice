# ADR 0020: Deferred Font Pack — Small Payload, Fetch mPDF Fonts On Demand

## Status

proposed

## Context

The Tier A release artifact (ADR 0003) is a self-contained ZIP the operator drops
onto shared hosting and configures via `install.php` (ADR 0015). Measured today it
is **~74 MB**, and profiling shows the size is almost entirely PDF **fonts**:

- `vendor/mpdf/mpdf/ttfonts` is **~88 MB uncompressed** — the dominant component.
  The rest of the production tree (nene2 ~8 MB, mpdf code ~6 MB, phpmailer, the
  built SPA, `src/`) is small.
- Empirically (rendering real 見積 / 請求 PDFs): document **body text** uses mPDF's
  `mode=ja` default font **`Sun-ExtA` (22 MB)**, and **`Sun-ExtB` (17 MB)** is pulled
  in for CJK-Ext-B kanji that occur in real Japanese names (e.g. 𠮷田). **Headings**
  use the app-bundled **IPAexGothic / IPAexMincho** (`resources/fonts/`, IPA Font
  License). All other bundled fonts (Korean, Arabic, Tibetan, ancient scripts, …)
  are never used for Japanese invoices.

This creates a concrete deployment failure: Japanese shared hosts (heteml, sakura,
Xserver, …) cap `upload_max_filesize` / `post_max_size` (commonly **2–50 MB**), so
the manual-upload install path (ADR 0018's first-class fallback) **cannot upload a
74 MB payload** on many hosts.

Two facts shape the fix: **(1)** fonts are needed **only at PDF-render time** — not
at install, login, or day-to-day operation; **(2)** static slimming is capped —
keeping the current invoice appearance means keeping `Sun-ExtA + Sun-ExtB` (39 MB),
so dropping only the unused exotic fonts lands at ~50 MB (a weak win). Dropping the
CJK fonts too would require switching the invoice **body** font to IPAex — a change
to the flagship legal document's appearance, deliberately declined for now.

### Constraints

- **Tier A**: no shell, no Composer, no CLI, no cron, per-request PHP (ADR 0003).
  `openssl` is available; outbound HTTPS is usually available but **some hosts block
  it** — so fetching cannot be the only path (mirrors ADR 0018).
- **Accounting compliance is binding** (`docs/explanation/accounting-compliance.md`):
  an issued 適格請求書 must render correctly. A tofu / wrong-glyph invoice is
  unacceptable — font handling must be **fail-closed**, never emitting a document
  with missing glyphs.
- **One artifact, any location** (ADR 0015) and **install ≙ update** (ADR 0018) must
  be preserved.
- **Fonts are already redistributed** in the release ZIP today; moving them into a
  separately-hosted pack raises no new licensing question versus the status quo.

### Options considered

1. **Ship everything (status quo).** Rejected: 74 MB blocks manual upload on the
   Tier A hosts the installer exists for.
2. **Static slim — drop only unused fonts, keep appearance.** Rejected as
   insufficient: `Sun-ExtA + Sun-ExtB` (39 MB) are both in use for Japanese body
   text, so the floor is ~50 MB — still over many upload limits.
3. **Switch body font to IPAex + drop CJK fonts (~20 MB).** Rejected for now: it
   changes the rendered appearance of the flagship invoice/quote (a product/visual
   decision), even though it is compliance-neutral and better Japanese typography.
   Left available as a future option.
4. **Deferred font pack (chosen).** Exclude the large fonts from the payload and
   fetch them **server-side, on demand**, verified and cached — keeping the code
   payload small enough to install anywhere while preserving the current appearance.

## Decision

Adopt a **deferred font pack**: the release payload ships only the fonts mPDF needs
to boot plus the small app-bundled IPAex; the **large PDF fonts are a separate,
signed, server-fetched artifact** acquired on demand and cached, with rendering
**fail-closed** until they are present.

- **Payload excludes the large fonts.** `build-release.sh` omits the bulk of
  `vendor/mpdf/mpdf/ttfonts` (the CJK / exotic fonts). What stays bundled is only
  what is needed to (a) boot mPDF and (b) render Latin — the exact minimal set
  (e.g. the DejaVu family, mPDF's default/backup) is fixed at implementation. IPAex
  stays bundled (`resources/fonts/`, small, needed for headings). The resulting
  payload target is **well under typical shared-hosting upload limits**.
- **Font pack = a separate artifact at the Origin (ADR 0018 family).** The large
  fonts are published as a versioned pack at the NeNe Official Origin / CDN,
  **integrity-verified** on the client with the same posture as ADR 0018
  (SHA-256 + signature against the bundled public key; refuse on mismatch).
- **Acquisition — two paths, shared verify/apply (mirrors ADR 0018).**
  - **Auto-fetch (default):** PHP fetches the pack over HTTPS into a **writable
    cache** (`var/fonts/`), which is added to mPDF's `fontDir`. May be triggered by
    an explicit installer / admin "fetch fonts" step and/or lazily on first PDF
    render.
  - **Manual upload (first-class fallback):** the operator uploads the font-pack ZIP
    through the UI (same verify + extract), for hosts with blocked outbound HTTPS.
- **Fail-closed rendering (compliance).** PDF generation checks that every required
  font is present **before** rendering. If a required font is missing and cannot be
  acquired, generation **refuses with a clear, actionable error** (and points to the
  manual-upload path) — it must **never** emit a tofu / wrong-glyph invoice. The
  current appearance (Sun-ExtA/B body, IPAex headings) is unchanged once the pack is
  present.
- **Cache + updates.** The pack is content-addressed / versioned; a font-pack update
  is fetched and cached like a release update (ADR 0018 discipline), and the cache
  survives app updates.

This ADR records the **direction and constraints**. The exact bundled-vs-fetched
font split, the acquisition trigger (explicit step vs lazy-on-first-PDF vs both),
the pack manifest/coordinates, and the identifier names are settled at
implementation and **registered in `docs/explanation/terminology.md` in the
implementing PR**.

## Consequences

**Benefits**

- The code payload drops to a size that **installs (and manual-uploads) on any Tier
  A host**, fixing the concrete upload-limit failure — without changing invoice
  appearance.
- Fonts are fetched **only when PDFs are actually generated**, and server-side fetch
  is **not bounded by upload limits** (the true blocker was the browser upload, not
  outbound bandwidth).
- Reuses ADR 0018's origin + verify + manual-fallback model; the font pack is "just
  another signed artifact," keeping one integrity posture and one fallback story.
- Leaves the future IPAex-body option (Option 3) open as an independent typography
  decision, decoupled from the size problem.

**Costs / risks**

- **A PDF cannot be produced until the pack is present** — first-PDF latency (a fetch)
  or an explicit setup step; the fail-closed guard turns a missing pack into a clear
  error rather than a broken invoice, but it is a new operational state to design and
  test.
- **Outbound HTTPS is not guaranteed** — the manual font-pack upload must be a tested,
  first-class flow (the pack is still tens of MB, so upload-limit hosts that also
  block HTTPS need the pack split or a documented minimum limit).
- **New Origin/CDN responsibility**: the font pack must be hosted, versioned, and
  verifiable before the client is useful (shared with ADR 0018's Origin follow-ups;
  the Origin's `/v1` read path + CDN are not yet live).
- **More moving parts** than a static payload: fetch, verify, cache, `fontDir`
  wiring, fail-closed gate, and cache invalidation on font-pack updates.

**Follow-up** (separate issues)

- Build: `build-release.sh` excludes the large fonts; produce + publish the font-pack
  artifact (checksum + signature).
- Product: server-side fetch (into `var/fonts/`) + integrity verify + cache; add the
  cache dir to mPDF `fontDir`; the fail-closed pre-render font check; acquisition
  trigger (installer step and/or lazy-on-first-PDF); manual font-pack upload fallback.
- Origin (private): host + sign the font-pack manifest/artifact (shared with ADR 0018).
- Terminology: register the font-pack / `fontDir` / manifest identifiers in the
  implementing PR.
- Docs: update the install guide and ADR 0003 (payload composition) / ADR 0015
  (install-anywhere) narratives.

## Related

- Issue: `#548`
- PR: `#000`
- Related: ADR 0003 (dual deployment / release ZIP), ADR 0015 (location-independent
  install), ADR 0018 (self-update via signed artifacts and Origin — same verify /
  fallback posture), `docs/explanation/accounting-compliance.md` (PDF correctness /
  fail-closed)
- Supersedes: none
- Superseded by: none
