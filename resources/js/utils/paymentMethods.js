// Helpers for displaying a member's payment methods (type checks, Mollie link
// state, status badges). Kept free of components so they can be unit tested.

export const isSepaType = type =>
  type === 'sepa_direct_debit' || type === 'mollie_directdebit'

export const isCreditCardType = type =>
  type === 'creditcard' || type === 'mollie_creditcard' || Boolean(type?.includes('creditcard'))

export const isBankTransferType = type =>
  type === 'banktransfer' || type === 'mollie_banktransfer' || Boolean(type?.includes('banktransfer'))

// Both ids together mean Mollie holds the account data and the mandate: the
// IBAN stored locally is only a copy that must not be edited on our side.
export const isMollieManaged = paymentMethod =>
  Boolean(paymentMethod?.mollie_customer_id && paymentMethod?.mollie_mandate_id)

// A Mollie direct debit is supposed to carry both ids. Missing either one means
// the method cannot be collected through Mollie, which the operator has to fix.
export const isMollieLinkBroken = paymentMethod =>
  paymentMethod?.type === 'mollie_directdebit' && !isMollieManaged(paymentMethod)

// Mollie stores the account data itself and the local iban is cleared once a
// mandate is in place. A value sitting here again means the operator corrected
// the IBAN afterwards, so it still has to be pushed over to Mollie.
export const canSyncMollieMandate = paymentMethod =>
  paymentMethod?.type === 'mollie_directdebit' &&
  paymentMethod?.sepa_mandate_status === 'active' &&
  Boolean(paymentMethod?.iban)

// Mollie stores its own identifiers for a direct debit; they are shown next to
// the account data whenever Mollie knows this payment method at all.
export const hasMollieIds = paymentMethod =>
  Boolean(paymentMethod?.mollie_customer_id || paymentMethod?.mollie_mandate_id)

const STATUS_CLASSES = {
  active: 'bg-success-soft text-success-strong',
  inactive: 'bg-fill text-ink',
  paused: 'bg-warning-soft text-warning-strong',
  cancelled: 'bg-danger-soft text-danger-strong',
  paid: 'bg-success-soft text-success-strong',
  pending: 'bg-orange-100 text-orange-800',
  failed: 'bg-danger-soft text-danger-strong',
  expired: 'bg-fill text-ink'
}

const STATUS_TEXTS = {
  active: 'Aktiv',
  inactive: 'Inaktiv',
  paused: 'Pausiert',
  cancelled: 'Gekündigt',
  paid: 'Bezahlt',
  pending: 'Ausstehend',
  failed: 'Fehlgeschlagen',
  expired: 'Abgelaufen'
}

export const getPaymentMethodStatusClass = status => STATUS_CLASSES[status] || 'bg-fill text-ink'

export const getPaymentMethodStatusText = status => STATUS_TEXTS[status] || status

const ICON_CLASSES = {
  sepa_direct_debit: 'bg-info-soft text-info',
  creditcard: 'bg-purple-100 text-purple-600',
  banktransfer: 'bg-success-soft text-success',
  cash: 'bg-warning-soft text-warning',
  invoice: 'bg-fill text-ink-secondary'
}

export const getPaymentMethodIconClass = type => ICON_CLASSES[type] || 'bg-fill text-ink-secondary'

const SEPA_MANDATE_STATUS_CLASSES = {
  pending: 'bg-warning-soft text-warning-strong',
  signed: 'bg-info-soft text-info-strong',
  active: 'bg-success-soft text-success-strong',
  revoked: 'bg-danger-soft text-danger-strong',
  expired: 'bg-fill text-ink'
}

const SEPA_MANDATE_STATUS_TEXTS = {
  pending: 'Unterschrift ausstehend',
  signed: 'Unterschrieben',
  active: 'Aktiv',
  revoked: 'Widerrufen',
  expired: 'Abgelaufen'
}

export const getSepaMandateStatusClass = status => SEPA_MANDATE_STATUS_CLASSES[status] || 'bg-fill text-ink'

export const getSepaMandateStatusText = status => SEPA_MANDATE_STATUS_TEXTS[status] || status
