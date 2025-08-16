import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

export function useInertiaPayments(memberId) {
  // Inertia Page Object korrekt initialisieren
  const page = usePage()

  const executingPaymentId = ref(null)
  const executingBatch = ref(false)

  // Reactive Payments aus Inertia Page Props
  const payments = computed(() => {
    return page.props.member?.payments || []
  })

  const updateLocalPayments = (updatedPayments) => {
    // Sichere Aktualisierung der Inertia Page Props
    if (page.props.member) {
      page.props.member.payments = updatedPayments
    }

    // Optional: Custom Event für weitere Components
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('payments-updated', {
        detail: { payments: updatedPayments, memberId }
      }))
    }
  }

  const executePayment = (payment) => {
    if (!confirm(`Möchten Sie die Zahlung "${payment.description}" jetzt ausführen?`)) {
      return Promise.resolve(false)
    }

    executingPaymentId.value = payment.id

    return new Promise((resolve, reject) => {
      router.post(route('members.payments.execute', {
        member: memberId,
        payment: payment.id
      }), {}, {
        preserveScroll: true,
        onSuccess: (responsePage) => {
          executingPaymentId.value = null

          // Prüfe auf optimierte Response
          if (responsePage.props.updatedPayments) {
            updateLocalPayments(responsePage.props.updatedPayments)
            resolve({
              success: true,
              payments: responsePage.props.updatedPayments,
              message: responsePage.props.flash.message
            })
          } else {
            // Fallback: Partial reload
            router.reload({
              only: ['member'],
              preserveScroll: true,
              onSuccess: () => {
                resolve({ success: true, reloaded: true })
              },
              onError: reject
            })
          }
        },
        onError: (errors) => {
          executingPaymentId.value = null
          reject(errors)
        }
      })
    })
  }

  const executeBatchPayments = (paymentIds) => {
    const count = paymentIds.length
    if (!confirm(`Möchten Sie ${count} ausstehende Zahlung(en) jetzt ausführen?`)) {
      return Promise.resolve(false)
    }

    executingBatch.value = true

    return new Promise((resolve, reject) => {
      router.post(route('members.payments.execute-batch', memberId), {
        payment_ids: paymentIds
      }, {
        preserveScroll: true,
        onSuccess: (responsePage) => {
          executingBatch.value = false

          // Prüfe auf optimierte Response
          if (responsePage.props.updatedPayments) {
            updateLocalPayments(responsePage.props.updatedPayments)
            resolve({
              success: true,
              payments: responsePage.props.updatedPayments,
              message: responsePage.props.flash.message,
              count: count
            })
          } else {
            // Fallback: Partial reload
            router.reload({
              only: ['member'],
              preserveScroll: true,
              onSuccess: () => {
                resolve({ success: true, reloaded: true, count: count })
              },
              onError: reject
            })
          }
        },
        onError: (errors) => {
          executingBatch.value = false
          reject(errors)
        }
      })
    })
  }

  // Computed states
  const isExecuting = computed(() => {
    return executingPaymentId.value !== null || executingBatch.value
  })

  const isPaymentExecuting = (paymentId) => {
    return executingPaymentId.value === paymentId
  }

  return {
    // State
    payments,
    executingPaymentId,
    executingBatch,

    // Methods
    executePayment,
    executeBatchPayments,
    updateLocalPayments,

    // Computed
    isExecuting,
    isPaymentExecuting
  }
}
