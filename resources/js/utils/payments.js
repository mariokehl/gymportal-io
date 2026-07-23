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

/**
 * Today in local time as YYYY-MM-DD. Both the execution and the due date are
 * plain dates (Y-m-d), so comparing them as strings avoids any timezone shift.
 */
export const todayAsIsoDate = () => {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - offset).toISOString().slice(0, 10)
}

/**
 * A payment is scheduled when its execution or due date still lies ahead.
 * Such rows are highlighted in the table to separate them from the ones that
 * are already due.
 */
export const isScheduledInFuture = (payment, today = todayAsIsoDate()) => {
  const dates = [payment?.execution_date, payment?.due_date]
    .filter(Boolean)
    .map(date => String(date).slice(0, 10))

  return dates.some(date => date > today)
}

/**
 * The date a payment is scheduled for: the execution date, falling back to the
 * due date. Returns an empty string when neither is set.
 */
export const scheduledDate = (payment) => {
  const date = payment?.execution_date || payment?.due_date
  return date ? String(date).slice(0, 10) : ''
}

/**
 * Sorts payments by their scheduled date, newest first, with the highest id as
 * the tie-breaker. Mirrors the server-side ordering used on the finances page
 * so both views present payments in the same order.
 */
export const sortByScheduledDate = (payments) => {
  return [...payments].sort((a, b) => {
    const dateA = scheduledDate(a)
    const dateB = scheduledDate(b)

    // Payments without any date sort last, regardless of their id.
    if (dateA !== dateB) {
      if (!dateA) return 1
      if (!dateB) return -1
      return dateA < dateB ? 1 : -1
    }

    return b.id - a.id
  })
}

export const getMemberInitials = (member) => {
  if (!member) return '??'
  const first = member.first_name?.charAt(0) || ''
  const last = member.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
}
