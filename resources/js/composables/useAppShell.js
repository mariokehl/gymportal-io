import { reactive } from 'vue'

// Injection key shared by the persistent AppShell and the per-page AppLayout.
export const APP_SHELL_KEY = Symbol('appShell')

const DEFAULT_PAGE_OPTIONS = Object.freeze({
  // Hide the header user cluster (admin badge, notifications, profile) on
  // mobile so the page can surface its own actions there.
  hideHeaderUserOnMobile: false,
})

/**
 * Page-level options the shell renders but each page decides. The shell stays
 * mounted across Inertia visits, so the current page claims the options with a
 * token and releases them on unmount. A release from a page that is no longer
 * the owner (e.g. an old page unmounting after the new one mounted) is ignored.
 */
export function createAppShellState() {
  const pageOptions = reactive({ ...DEFAULT_PAGE_OPTIONS })
  let owner = null

  function claim(token, options = {}) {
    owner = token
    Object.assign(pageOptions, DEFAULT_PAGE_OPTIONS, options)
  }

  function release(token) {
    if (owner !== token) return

    owner = null
    Object.assign(pageOptions, DEFAULT_PAGE_OPTIONS)
  }

  return { pageOptions, claim, release }
}
