import { useToast } from '@/composables/useToast'

/**
 * Bridge server messages into the global toast system.
 *
 * Controllers redirect with ->with('success' | 'info' | 'warning' | 'error', …);
 * HandleInertiaRequests hands those to Inertia's flash data as
 * { toasts: [{ type, message }] }. Inertia sends flash data with exactly one
 * response and fires its "flash" event once for it — partial reloads, remounted
 * layouts and back navigation never replay a message. Do not watch page props
 * for messages; register this listener once when the app boots.
 */

const TOAST_TYPES = ['success', 'info', 'warning', 'error']

export function showFlashToasts(flash) {
  const toast = useToast()

  for (const { type, message } of flash?.toasts ?? []) {
    if (message) {
      toast[TOAST_TYPES.includes(type) ? type : 'success'](message)
    }
  }
}

/**
 * @param {{ on: Function }} router  Inertia router
 * @returns {Function} removes the listener
 */
export function registerFlashToasts(router) {
  return router.on('flash', (event) => showFlashToasts(event.detail.flash))
}
