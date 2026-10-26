// Keeps the active tab of a page in the `?tab=` query parameter, so it survives
// reloads, shared links and organization switches.

export const TAB_PARAM = 'tab'

/**
 * Returns the tab named in the query string when it is one of `allowed`,
 * otherwise the fallback (unknown or no longer permitted tabs).
 */
export function resolveTab(search, allowed, fallback) {
  const requested = new URLSearchParams(search).get(TAB_PARAM)

  return allowed.includes(requested) ? requested : fallback
}

/**
 * Builds the relative URL for `href` with `tab` applied. The default tab is
 * left out to keep plain links clean.
 */
export function urlWithTab(href, tab, fallback) {
  const url = new URL(href)

  if (tab === fallback) {
    url.searchParams.delete(TAB_PARAM)
  } else {
    url.searchParams.set(TAB_PARAM, tab)
  }

  return url.pathname + url.search + url.hash
}
