import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// The composable talks to Inertia's router and Ziggy's global route() helper;
// both are stubbed so the actions can be exercised without a browser.
const put = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { put: (...args) => put(...args) } }))

const { useMembershipActions } = await import('./useMembershipActions')

const MEMBER_ID = 7
const membership = { id: 42 }

/** Runs the request the composable handed to the router. */
const lastCall = () => {
  const [url, data, options] = put.mock.calls.at(-1)
  return { url, data, options }
}

beforeEach(() => {
  put.mockClear()
  globalThis.route = (name, params) => `${name}:${params.member}/${params.membership}`
  globalThis.confirm = vi.fn(() => true)
  globalThis.alert = vi.fn()
})

afterEach(() => {
  delete globalThis.route
})

describe('loading state', () => {
  it('flags the membership while a forced status change is in flight', () => {
    const actions = useMembershipActions(MEMBER_ID)

    expect(actions.forcingMembershipStatus.value).toBeNull()

    actions.forceMembershipStatus(membership, 'expired')

    // The id is what MembershipStatusEditor compares against to show its spinner.
    expect(actions.forcingMembershipStatus.value).toBe(membership.id)

    lastCall().options.onSuccess()
    expect(actions.forcingMembershipStatus.value).toBeNull()
  })

  it('clears the flag when the request fails', () => {
    const actions = useMembershipActions(MEMBER_ID)

    actions.forceMembershipStatus(membership, 'expired')
    lastCall().options.onError({})

    expect(actions.forcingMembershipStatus.value).toBeNull()
  })

  it('tracks each direct action with its own ref', () => {
    const actions = useMembershipActions(MEMBER_ID)

    const cases = [
      ['activateMembership', 'activatingMembership'],
      ['resumeMembership', 'resumingMembership'],
      ['abortMembership', 'abortingMembership'],
      ['revokeCancellation', 'revokingCancellation'],
    ]

    for (const [action, busyRef] of cases) {
      actions[action](membership)
      expect(actions[busyRef].value, action).toBe(membership.id)
      lastCall().options.onSuccess()
      expect(actions[busyRef].value, action).toBeNull()
    }
  })

  it('does not expose refs for the modal-driven actions', () => {
    const actions = useMembershipActions(MEMBER_ID)

    // Pause and cancel report progress through their modal's own form state.
    expect(actions.pausingMembership).toBeUndefined()
    expect(actions.cancellingMembership).toBeUndefined()
  })
})

describe('requests', () => {
  it('targets the right route and passes the forced status', () => {
    const actions = useMembershipActions(MEMBER_ID)

    actions.forceMembershipStatus(membership, 'expired')

    expect(lastCall().url).toBe('members.memberships.force-status:7/42')
    expect(lastCall().data).toEqual({ status: 'expired' })
  })

  it('sends the force flag when withdrawing', () => {
    const actions = useMembershipActions(MEMBER_ID)

    actions.withdrawMembership(membership, true)

    expect(lastCall().url).toBe('members.memberships.withdraw:7/42')
    expect(lastCall().data).toEqual({ force: true })
  })

  it('runs the onSuccess hook after a withdrawal', () => {
    const actions = useMembershipActions(MEMBER_ID)
    const onSuccess = vi.fn()

    actions.withdrawMembership(membership, false, { onSuccess })
    lastCall().options.onSuccess()

    expect(onSuccess).toHaveBeenCalledOnce()
  })

  it('notifies the caller once a membership was activated', () => {
    const onActivated = vi.fn()
    const actions = useMembershipActions(MEMBER_ID, { onActivated })

    actions.activateMembership(membership)
    lastCall().options.onSuccess()

    expect(onActivated).toHaveBeenCalledOnce()
  })
})

describe('confirmation', () => {
  it('does not send anything when the operator cancels', () => {
    globalThis.confirm = vi.fn(() => false)
    const actions = useMembershipActions(MEMBER_ID)

    actions.forceMembershipStatus(membership, 'expired')

    expect(put).not.toHaveBeenCalled()
    expect(actions.forcingMembershipStatus.value).toBeNull()
  })
})

describe('error reporting', () => {
  it('prefers the concrete backend message over the generic fallback', () => {
    const actions = useMembershipActions(MEMBER_ID)

    actions.activateMembership(membership)
    lastCall().options.onError({
      payment_method: 'Legen Sie mindestens eine Zahlungsart an.',
    })

    expect(globalThis.alert).toHaveBeenCalledWith('Legen Sie mindestens eine Zahlungsart an.')
  })

  it('falls back to the generic message when the backend sends none', () => {
    const actions = useMembershipActions(MEMBER_ID)

    actions.activateMembership(membership)
    lastCall().options.onError({})

    expect(globalThis.alert).toHaveBeenCalledWith('Die Mitgliedschaft konnte nicht aktiviert werden.')
  })
})
