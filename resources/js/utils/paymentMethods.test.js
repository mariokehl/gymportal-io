import { describe, it, expect } from 'vitest'
import {
  canSyncMollieMandate, getPaymentMethodIconClass, getPaymentMethodStatusClass,
  getPaymentMethodStatusText, getSepaMandateStatusClass, getSepaMandateStatusText,
  hasMollieIds, isBankTransferType, isCreditCardType, isMollieLinkBroken,
  isMollieManaged, isSepaType
} from '@/utils/paymentMethods'

const mollieDirectDebit = (overrides = {}) => ({
  type: 'mollie_directdebit',
  sepa_mandate_status: 'active',
  iban: null,
  mollie_customer_id: 'cst_1',
  mollie_mandate_id: 'mdt_1',
  ...overrides
})

describe('payment method types', () => {
  it('recognises SEPA direct debits', () => {
    expect(isSepaType('sepa_direct_debit')).toBe(true)
    expect(isSepaType('mollie_directdebit')).toBe(true)
    expect(isSepaType('creditcard')).toBe(false)
  })

  it('recognises credit cards and bank transfers', () => {
    expect(isCreditCardType('mollie_creditcard')).toBe(true)
    expect(isCreditCardType('cash')).toBe(false)
    expect(isCreditCardType(undefined)).toBe(false)
    expect(isBankTransferType('banktransfer')).toBe(true)
    expect(isBankTransferType('invoice')).toBe(false)
  })
})

describe('Mollie link state', () => {
  it('treats a method with customer and mandate as managed by Mollie', () => {
    expect(isMollieManaged(mollieDirectDebit())).toBe(true)
    expect(isMollieLinkBroken(mollieDirectDebit())).toBe(false)
  })

  it('flags a Mollie direct debit missing an id as broken', () => {
    const method = mollieDirectDebit({ mollie_mandate_id: null })

    expect(isMollieManaged(method)).toBe(false)
    expect(isMollieLinkBroken(method)).toBe(true)
    expect(hasMollieIds(method)).toBe(true)
  })

  it('does not flag a plain SEPA direct debit as broken', () => {
    const method = { type: 'sepa_direct_debit' }

    expect(isMollieLinkBroken(method)).toBe(false)
    expect(hasMollieIds(method)).toBe(false)
  })

  it('offers a sync only for an active mandate with a corrected IBAN', () => {
    expect(canSyncMollieMandate(mollieDirectDebit({ iban: 'DE89370400440532013000' }))).toBe(true)
    expect(canSyncMollieMandate(mollieDirectDebit())).toBe(false)
    expect(canSyncMollieMandate(mollieDirectDebit({ iban: 'DE89370400440532013000', sepa_mandate_status: 'pending' }))).toBe(false)
  })
})

describe('status labels', () => {
  it('maps known statuses and falls back for unknown ones', () => {
    expect(getPaymentMethodStatusText('active')).toBe('Aktiv')
    expect(getPaymentMethodStatusText('unknown')).toBe('unknown')
    expect(getPaymentMethodStatusClass('unknown')).toBe('bg-fill text-ink')
    expect(getSepaMandateStatusText('pending')).toBe('Unterschrift ausstehend')
    expect(getSepaMandateStatusClass('revoked')).toBe('bg-danger-soft text-danger-strong')
    expect(getPaymentMethodIconClass('mollie_paypal')).toBe('bg-fill text-ink-secondary')
  })
})
