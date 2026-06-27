# Milestone: Financial Cluster + Recurring Billing (2026-06)

Goal: turn NeNe Invoice from a standalone billing app into the **foundation of the
NeNe financial cluster** — recurring billing (the persona-review "next move"), a
live upstream connection to NeNe Clear, and the design groundwork for managed-cloud
delivery (Suite federation, MFA).

**Status: in progress (2026-06-28)**

## Context

Ecosystem strategy (`../_work/discussion-log/2026-06-27.md`): **NeNe Clear** is the
first cash wedge (reconciliation/dunning ROI), **NeNe Invoice is the financial-cluster
foundation** (billing SSOT), and **NeNe Suite** is the managed-cloud amplifier
(free-trial / VPS-migration / paid-guarantee — the M2 "managed-first" model).

Three independent analyses converged on **managed-first** (drop self-host/data-sovereignty
messaging for the SMB ICP): this repo's persona panels, the `_work` pricing panel③,
and nene-clear's PR #206 (retract Tier-A shared hosting → VPS+Docker+managed).

## Acceptance Criteria

- [x] Recurring billing — persistence (#519), draft generation (#520), CRUD use cases (#521),
      admin API + OpenAPI (#522), `/recurring` admin UI (#523) — all merged
- [x] `organizations.external_id` federation finder (#492 / PR #498) — first step of the
      federation epic (#492–#497)
- [x] Backend boundary-value test expansion (+152 cases, #499) + registration-number `\z`
      hardening (#500)
- [x] Clear↔Invoice upstream connection **contract-verified live** (clear PR #215: fixed
      consumer-side nested-payload + comma-status bugs; 6/6 contract tests green)
- [x] MFA (standalone TOTP) design accepted — #524, `docs/design/mfa-totp.md`
      (conforms to Suite ADR 0025)
- [ ] **Recurring execution route (cron/CLI/request-time due) — #526 (P0)** — the
      headline feature does not auto-run yet
- [ ] Recurring auto-issue (numbering + qualified invoice) — gated on tax sign-off (#503)
- [ ] Federation epic — NENE_SUITE_MODE (#493), JWKS assertion + JIT (#494),
      join/leave + org-link UI (#495), `/machine/health` (#496), candidate-db preflight (#497)
- [ ] MFA implementation (ADR 0019 → backend → enrol API → login step-up → UI)

## Persona validation (R1 → R4)

| Round | 採用 | 検討 | 見送り | 支払 | note |
| --- | --- | --- | --- | --- | --- |
| R1 (baseline) | 0 | 2 | 8 | 3 | self-host wall blocks 8/10 |
| R3 (recurring only) | 1 | 1 | 8 | 3 | moved 1 technical persona |
| R4 (full update) | 1 | 9 | 0 | 3 | **managed cloud broke the wall (見送り 8→0)**, but no new adoption/payment |

**Read:** managed cloud (Suite) is the *necessary* condition for consideration, not the
*sufficient* condition for adoption/payment. Conversion needs feature completion — top
priority = recurring execution route (#526), then bank auto-reconcile (#505), bulk
issue/email (#527), industry templates (#528 / #513). MFA was cited by **0/10** as a
decider — real but not the immediate priority. Reports: `docs/research/persona-review-2026-06-27/`.

## Follow-up Milestone

Conversion push (turn the 9 "検討" into paid): execution route → bank auto-reconcile →
bulk processing → industry templates; in parallel, the federation epic to make Invoice
run in the Suite managed cloud, and MFA for enterprise trust.
