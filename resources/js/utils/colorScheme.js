/**
 * Light/dark handling for the backend.
 *
 * The user's preference ("light", "dark" or "system") is rendered onto <html>
 * by app.blade.php, which also resolves it before the first paint. These
 * helpers keep it in sync afterwards: when the preference changes, and when
 * the operating system switches while the preference is "system".
 */

export const THEME_PREFERENCES = ['light', 'dark', 'system']

export function normalizePreference(preference) {
  return THEME_PREFERENCES.includes(preference) ? preference : 'system'
}

export function resolveScheme(preference, prefersDark) {
  const normalized = normalizePreference(preference)
  if (normalized === 'system') return prefersDark ? 'dark' : 'light'
  return normalized
}

/**
 * Apply a preference to the root element and return the resolved scheme.
 */
export function applyScheme(root, preference, prefersDark) {
  const normalized = normalizePreference(preference)
  const scheme = resolveScheme(normalized, prefersDark)

  root.dataset.themePreference = normalized
  root.classList.toggle('dark', scheme === 'dark')
  root.style.colorScheme = scheme

  return scheme
}

/**
 * Follow OS changes while the stored preference is "system".
 * Returns a function that stops listening.
 */
export function watchSystemScheme(root, mediaQueryList) {
  const onChange = (event) => {
    if (root.dataset.themePreference === 'system') {
      applyScheme(root, 'system', event.matches)
    }
  }

  mediaQueryList.addEventListener('change', onChange)
  return () => mediaQueryList.removeEventListener('change', onChange)
}
