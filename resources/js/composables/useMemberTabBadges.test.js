import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope, ref } from 'vue'

import { useMemberTabBadges } from './useMemberTabBadges'

/**
 * The composable registers onBeforeUnmount, which Vue only accepts inside a
 * component setup. An effect scope gives the computeds and the watcher a place
 * to live without pulling in a full component harness; the lifecycle warning
 * that comes with it is expected and muted below.
 */
const scopes = []

const badges = (member, outstandingBalance = null) => {
  const scope = effectScope()
  scopes.push(scope)

  return scope.run(() => useMemberTabBadges(member, ref(outstandingBalance)))
}

const sepa = (overrides = {}) => ({
  status: 'active',
  requires_mandate: true,
  sepa_mandate_status: 'active',
  ...overrides,
})

beforeEach(() => {
  vi.spyOn(console, 'warn').mockImplementation(() => {})
})

afterEach(() => {
  scopes.splice(0).forEach(scope => scope.stop())
  vi.restoreAllMocks()
})

describe('unusablePaymentMethodCount', () => {
  it('counts a method whose SEPA mandate is still waiting for activation', () => {
    const { unusablePaymentMethodCount } = badges({
      payment_methods: [sepa({ status: 'pending', sepa_mandate_status: 'pending' })],
    })

    expect(unusablePaymentMethodCount.value).toBe(1)
  })

  it('ignores expired methods, which the operator can no longer activate', () => {
    const { unusablePaymentMethodCount } = badges({
      payment_methods: [sepa({ status: 'expired', sepa_mandate_status: 'revoked' })],
    })

    expect(unusablePaymentMethodCount.value).toBe(0)
  })

  it('still counts the actionable methods next to an expired one', () => {
    const { unusablePaymentMethodCount } = badges({
      payment_methods: [
        sepa({ status: 'expired', sepa_mandate_status: 'revoked' }),
        sepa({ status: 'active', sepa_mandate_status: 'signed' }),
      ],
    })

    expect(unusablePaymentMethodCount.value).toBe(1)
  })

  it('leaves an active method without a mandate requirement uncounted', () => {
    const { unusablePaymentMethodCount } = badges({
      payment_methods: [{ status: 'active', requires_mandate: false }],
    })

    expect(unusablePaymentMethodCount.value).toBe(0)
  })
})

describe('member sources', () => {
  it('tracks a getter when the record is replaced', () => {
    // Inertia hands the page a brand new member object on every visit, so the
    // badges have to follow the getter rather than the object it returned once.
    const member = ref({ payment_methods: [sepa()] })
    const { unusablePaymentMethodCount, paymentsAttentionSignal } = badges(() => member.value)

    expect(paymentsAttentionSignal.value).toBeNull()

    member.value = { payment_methods: [sepa({ status: 'pending', sepa_mandate_status: 'pending' })] }

    expect(unusablePaymentMethodCount.value).toBe(1)
    expect(paymentsAttentionSignal.value?.key).toBe('payment-method')
  })

  it('tracks a ref when the record is replaced', () => {
    const member = ref({ payment_methods: [sepa()] })
    const { unusablePaymentMethodCount } = badges(member)

    member.value = { payment_methods: [sepa({ status: 'pending', sepa_mandate_status: 'pending' })] }

    expect(unusablePaymentMethodCount.value).toBe(1)
  })

  it('still accepts a plain record', () => {
    const { unusablePaymentMethodCount } = badges({
      payment_methods: [sepa({ status: 'pending', sepa_mandate_status: 'pending' })],
    })

    expect(unusablePaymentMethodCount.value).toBe(1)
  })
})

describe('payments attention signals', () => {
  it('does not flag the tab for an expired method alongside a usable one', () => {
    const { paymentsAttentionSignal } = badges({
      payment_methods: [sepa({ status: 'expired', sepa_mandate_status: 'revoked' }), sepa()],
    })

    expect(paymentsAttentionSignal.value).toBeNull()
  })

  it('reports a missing payment method when only an expired one remains', () => {
    // The method is unusable, so the member cannot be billed — but the hint has
    // to say the slot is empty rather than promise an activation that is gone.
    const { hasNoUsablePaymentMethod, paymentsAttentionHint } = badges({
      payment_methods: [sepa({ status: 'expired', sepa_mandate_status: 'revoked' })],
    })

    expect(hasNoUsablePaymentMethod.value).toBe(true)
    expect(paymentsAttentionHint.value).toBe('Keine Zahlungsart hinterlegt')
  })

  it('asks for an activation when a method is merely waiting for its mandate', () => {
    const { paymentsAttentionHint } = badges({
      payment_methods: [sepa({ status: 'pending', sepa_mandate_status: 'pending' })],
    })

    expect(paymentsAttentionHint.value).toBe(
      'Keine abrechenbare Zahlungsart: Zahlungsart bzw. SEPA-Mandat muss noch aktiviert werden'
    )
  })

  it('lists the outstanding balance next to the payment method reason', () => {
    const { paymentsAttentionHint } = badges({ payment_methods: [] }, 4990)

    expect(paymentsAttentionHint.value).toBe(
      'Offener Betrag durch Rücklastschrift · Keine Zahlungsart hinterlegt'
    )
  })
})
