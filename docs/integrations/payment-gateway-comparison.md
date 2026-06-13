# Card Payment Gateway Comparison (Receiver Side)

Research note feeding **Issue #429** (launch gateway selection) under
**ADR 0012** (card payment on issued invoices). This is decision input, not the
decision itself — the choice is recorded by promoting ADR 0012 to `accepted` or
by an addendum ADR.

> Figures below were checked **2026-06-13** from public pricing pages and are
> indicative only. Re-verify current rates before committing the decision in the
> ADR; processor pricing changes without notice.

## Inviolable constraint (from ADR 0012)

Whichever gateway is chosen, the self-host operator must stay at **PCI DSS
SAQ-A**: card PAN never touches the application, its database, or the operator's
server. Only **hosted redirect / processor-hosted iframe** flows are eligible.

⚠️ **SAQ-A is conditional, not automatic.** A processor-hosted page/iframe only
keeps you at SAQ-A if **no merchant-controlled scripts run on the payment page**
(e.g. a Google Tag Manager / analytics snippet on the checkout page expands
scope to SAQ-A-EP). Our payment-link page must therefore ship **without operator
analytics injection**. Document this as an operator constraint.

## Candidates

### Stripe Checkout

| Aspect | Notes |
| --- | --- |
| Flow | Prebuilt hosted page (redirect) or embeddable component; SAQ-A eligible |
| Processing fee (JP) | ~**3.6%** per successful charge, no fixed monthly fee |
| Currency / FX | JPY supported; Stripe may bill/settle with USD conversion — confirm settlement currency for JP accounts to avoid hidden FX |
| Webhooks | Mature, signed (`Stripe-Signature`), good idempotency story (event ids) |
| Docs / DX | Excellent; large ecosystem, strong test mode |
| Domestic fit (JP SMB) | Global brand; some JP SMBs prefer domestic invoicing/支払調書 ergonomics |

### PAY.JP

| Aspect | Notes |
| --- | --- |
| Flow | Hosted checkout / tokenized form (`payjp.js`); SAQ-A eligible with hosted token capture |
| Processing fee | From ~**2.59%** depending on plan; no setup / no monthly base on entry plan |
| Payout fee | ~**¥250** per payout (tax incl.) |
| Currency | JPY-native, domestic settlement (no FX layer) |
| Webhooks | Supported (async events incl. subscription/charge); signature verification model differs from Stripe — verify |
| Docs / DX | JP-language docs; smaller ecosystem than Stripe |
| Domestic fit (JP SMB) | Japan-domestic processor; aligns with our JP SMB target operator |

## Decision factors for #429

1. **Cost** — PAY.JP's entry rate (~2.59%) and JPY-native settlement (no FX,
   flat ¥250 payout) are attractive for JP SMB operators; Stripe (~3.6%) is
   higher and may add FX friction. Favors **PAY.JP** on price for the target user.
2. **SAQ-A discipline** — both qualify if we keep the payment page free of
   operator scripts. Neutral.
3. **DX / webhook robustness** — Stripe's signed-webhook + idempotency tooling
   and test mode are more mature; lowers risk for #431. Favors **Stripe**.
4. **Adapter portability** — `PaymentGatewayInterface` must absorb both signature
   schemes and currency handling. Designing the interface against the *harder*
   case (Stripe FX/settlement) first may yield a cleaner abstraction.
5. **Target-operator fit** — JP SMB self-host audience leans domestic
   (JPY-native, JP-language support). Favors **PAY.JP**.

## Tentative recommendation

Launch with **PAY.JP** as the first adapter (price + JPY-native settlement +
JP SMB fit), and design `PaymentGatewayInterface` so **Stripe Checkout** is a
straightforward second adapter (validates the abstraction; serves
international/Records-driven operators later).

This is a recommendation for discussion on #429 — not yet ratified. Ratify by
updating ADR 0012 (`proposed` → `accepted`) or an addendum ADR once agreed.

## Sources

- PAY.JP 料金: <https://pay.jp/plan>, 利用料金ヘルプ:
  <https://help.pay.jp/ja/articles/3438192>, Webhook 仕様:
  <https://pay.jp/docs/webhook>
- Stripe JP 料金: <https://stripe.com/en-jp/pricing>, Checkout:
  <https://stripe.com/en-gb-jp/payments/checkout>, SAQ-A 注意点（merchant
  scripts）: <https://cside.com/blog/can-you-use-stripe-for-pci-dss>
