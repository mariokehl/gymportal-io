import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Bundles the membership actions of the member detail page.
 *
 * Every action follows the same shape: confirm with the operator, flag the
 * membership as busy so its card can show a loading state, then PUT to the
 * matching endpoint and clear the flag again. The loading refs are returned so
 * the page can pass them straight into MembershipTab.
 *
 * @param {Number} memberId
 * @param {Object} [hooks]
 * @param {Function} [hooks.onActivated]  Called after a successful activation.
 */
export function useMembershipActions(memberId, { onActivated } = {}) {
  const resumingMembership = ref(null)
  const revokingCancellation = ref(null)
  const activatingMembership = ref(null)
  const abortingMembership = ref(null)
  const withdrawingMembership = ref(null)
  const forcingMembershipStatus = ref(null)

  /**
   * Runs one membership action: sets `busyRef` for the duration of the request
   * and surfaces the backend message when the request fails.
   */
  const runAction = (routeName, membershipId, busyRef, {
    confirmMessage = null,
    data = {},
    errorMessage = null,
    onSuccess = null,
  } = {}) => {
    if (confirmMessage && !confirm(confirmMessage)) {
      return
    }

    busyRef.value = membershipId

    router.put(route(routeName, { member: memberId, membership: membershipId }), data, {
      preserveScroll: true,
      onSuccess: () => {
        busyRef.value = null
        onSuccess?.()
      },
      onError: (errors) => {
        busyRef.value = null
        if (errorMessage) {
          // Prefer the concrete reason from the backend over the generic fallback
          alert(Object.values(errors || {})[0] || errorMessage)
        }
      },
    })
  }

  const activateMembership = (membership) => runAction(
    'members.memberships.activate', membership.id, activatingMembership, {
      confirmMessage: 'Möchten Sie diese Mitgliedschaft aktivieren?',
      errorMessage: 'Die Mitgliedschaft konnte nicht aktiviert werden.',
      onSuccess: () => onActivated?.(),
    }
  )

  const resumeMembership = (membership) => runAction(
    'members.memberships.resume', membership.id, resumingMembership, {
      confirmMessage: 'Möchten Sie diese Mitgliedschaft wirklich wieder aufnehmen?',
      errorMessage: 'Die Mitgliedschaft konnte nicht wieder aufgenommen werden.',
    }
  )

  const abortMembership = (membership) => runAction(
    'members.memberships.abort', membership.id, abortingMembership, {
      confirmMessage: 'Möchten Sie diesen Gratis-Testzeitraum wirklich abbrechen? Der Zeitraum wird sofort beendet.',
      errorMessage: 'Der Gratis-Testzeitraum konnte nicht abgebrochen werden.',
    }
  )

  const revokeCancellation = (membership) => runAction(
    'members.memberships.revoke-cancellation', membership.id, revokingCancellation, {
      confirmMessage: 'Möchten Sie die Kündigung wirklich zurücknehmen?',
      errorMessage: 'Die Kündigung konnte nicht zurückgenommen werden.',
    }
  )

  const withdrawMembership = (membership, force = false, { onSuccess } = {}) => runAction(
    'members.memberships.withdraw', membership.id, withdrawingMembership, {
      confirmMessage: 'Möchten Sie diese Mitgliedschaft wirklich widerrufen? Der Widerruf ist unwiderruflich und löst eine E-Mail-Bestätigung aus.',
      data: { force },
      onSuccess,
    }
  )

  const statusLabels = {
    active: 'Aktiv',
    expired: 'Abgelaufen',
    pending: 'Ausstehend',
  }

  const forceMembershipStatus = (membership, newStatus) => runAction(
    'members.memberships.force-status', membership.id, forcingMembershipStatus, {
      confirmMessage: `Möchten Sie den Status dieser Mitgliedschaft wirklich auf "${statusLabels[newStatus] || newStatus}" forcieren?`,
      data: { status: newStatus },
      errorMessage: 'Der Status konnte nicht geändert werden.',
    }
  )

  return {
    resumingMembership,
    revokingCancellation,
    activatingMembership,
    abortingMembership,
    withdrawingMembership,
    forcingMembershipStatus,
    activateMembership,
    resumeMembership,
    abortMembership,
    revokeCancellation,
    withdrawMembership,
    forceMembershipStatus,
  }
}
