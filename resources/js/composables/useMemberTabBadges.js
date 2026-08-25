import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { CircleAlert, TriangleAlert } from 'lucide-vue-next'

/**
 * Derives the attention badges shown on the member detail tab rail.
 *
 * The payments tab can be flagged for two independent reasons, each with its
 * own icon and colour. Outstanding balances outrank payment method issues, so
 * they are listed first and win whenever only a single signal can be shown.
 * When both apply the badge alternates between them, so neither signal stays
 * hidden behind the other.
 *
 * @param {import('vue').Ref<Object>|Object} member  The member record.
 * @param {import('vue').Ref<Number|null>} outstandingBalance  Unsettled chargeback total.
 * @param {Object} [options]
 * @param {Number} [options.alternateAfter]  Milliseconds between icon swaps.
 */
export function useMemberTabBadges(member, outstandingBalance, { alternateAfter = 3000 } = {}) {
  const memberValue = computed(() => (member?.value ?? member) ?? {})

  // Pending memberships waiting to be activated by the operator.
  const pendingMembershipCount = computed(
    () => (memberValue.value.memberships || []).filter(m => m.status === 'pending').length
  )

  // Payment methods that cannot be billed yet, mirroring the backend activation
  // guard: a method is usable when it is active and, if it requires a SEPA
  // mandate, that mandate is active too. Expired methods are left out: they are
  // closed records the operator cannot activate any more, so counting them would
  // flag the tab for something that needs no action.
  const unusablePaymentMethodCount = computed(
    () => (memberValue.value.payment_methods || []).filter(
      pm => pm.status !== 'expired'
        && (pm.status !== 'active' || (pm.requires_mandate && pm.sepa_mandate_status !== 'active'))
    ).length
  )

  // A pending membership can only be activated once a usable payment method
  // exists, so the payments tab is flagged when that is what blocks the operator.
  const hasNoUsablePaymentMethod = computed(() => {
    const methods = memberValue.value.payment_methods || []
    return !methods.some(
      pm => pm.status === 'active' && (!pm.requires_mandate || pm.sepa_mandate_status === 'active')
    )
  })

  const paymentsAttentionSignals = computed(() => {
    const signals = []

    if (outstandingBalance.value !== null) {
      signals.push({
        key: 'outstanding',
        icon: TriangleAlert,
        colorClass: 'bg-amber-100 text-amber-600',
        hint: 'Offener Betrag durch Rücklastschrift',
      })
    }

    if (hasNoUsablePaymentMethod.value) {
      signals.push({
        key: 'payment-method',
        icon: CircleAlert,
        colorClass: 'bg-orange-100 text-orange-700',
        hint: unusablePaymentMethodCount.value > 0
          ? 'Keine abrechenbare Zahlungsart: Zahlungsart bzw. SEPA-Mandat muss noch aktiviert werden'
          : 'Keine Zahlungsart hinterlegt',
      })
    } else if (unusablePaymentMethodCount.value > 0) {
      signals.push({
        key: 'payment-method',
        icon: CircleAlert,
        colorClass: 'bg-orange-100 text-orange-700',
        hint: `${unusablePaymentMethodCount.value} Zahlungsart(en) nicht aktiv`,
      })
    }

    return signals
  })

  const paymentsSignalIndex = ref(0)
  let paymentsSignalTimer = null

  const paymentsAttentionSignal = computed(
    () => paymentsAttentionSignals.value[paymentsSignalIndex.value] ?? paymentsAttentionSignals.value[0] ?? null
  )

  // The title lists every reason, even while the icon shows only one of them.
  const paymentsAttentionHint = computed(
    () => paymentsAttentionSignals.value.map(signal => signal.hint).join(' · ')
  )

  watch(
    () => paymentsAttentionSignals.value.length,
    (count) => {
      clearInterval(paymentsSignalTimer)
      paymentsSignalTimer = null
      paymentsSignalIndex.value = 0

      if (count > 1) {
        paymentsSignalTimer = setInterval(() => {
          paymentsSignalIndex.value = (paymentsSignalIndex.value + 1) % count
        }, alternateAfter)
      }
    },
    { immediate: true }
  )

  onBeforeUnmount(() => clearInterval(paymentsSignalTimer))

  return {
    pendingMembershipCount,
    unusablePaymentMethodCount,
    hasNoUsablePaymentMethod,
    paymentsAttentionSignal,
    paymentsAttentionHint,
  }
}
