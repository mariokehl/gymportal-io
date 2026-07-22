// utils/payments.js
// Shared payment helpers used by the payments table and the payment detail modal.

/**
 * Credit-redemption breakdown derived from a payment's metadata.
 * Returns null when the payment did not redeem any credit.
 */
export const getCreditRedemption = (payment) => {
  const meta = payment?.metadata
  if (!meta || !meta.credit_redeemed || !meta.credit_redeemed_cents) {
    return null
  }
  const redeemedCents = Number(meta.credit_redeemed_cents) || 0
  const originalCents = Number(meta.original_amount_cents) || redeemedCents
  const remainingCents = Math.max(0, originalCents - redeemedCents)
  // Original id of the ledger row in member_credit_ledgers.
  const evId = meta.credit_ledger_id != null ? `#${meta.credit_ledger_id}` : '—'
  return {
    evId,
    redeemedCents,
    remainingCents,
    balanceAfterCents: Number(meta.credit_balance_after_cents) || 0,
  }
}

export const hasCreditRedemption = (payment) => getCreditRedemption(payment) !== null

// A manually booked credit top-up ("Guthaben-Aufladung").
export const isCreditTopup = (payment) => payment?.is_credit_topup === true

// Source label for a top-up: "manuell erfasst · <staff name>".
export const creditTopupSource = (payment) => {
  const name = payment?.metadata?.created_by_name
  return name ? `manuell erfasst · ${name}` : 'manuell erfasst'
}

export const getMemberInitials = (member) => {
  if (!member) return '??'
  const first = member.first_name?.charAt(0) || ''
  const last = member.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
}
