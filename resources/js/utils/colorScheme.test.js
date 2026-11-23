import { describe, it, expect } from 'vitest'
import { applyScheme, normalizePreference, resolveScheme, watchSystemScheme } from './colorScheme'

// Minimal stand-ins for <html> and a MediaQueryList (Vitest runs in node).
const makeRoot = () => {
  const classes = new Set()
  return {
    dataset: {},
    style: {},
    classList: {
      toggle: (name, on) => (on ? classes.add(name) : classes.delete(name)),
      contains: name => classes.has(name),
    },
  }
}

const makeMediaQuery = () => {
  const target = new EventTarget()
  return {
    addEventListener: (type, fn) => target.addEventListener(type, fn),
    removeEventListener: (type, fn) => target.removeEventListener(type, fn),
    change(matches) {
      const event = new Event('change')
      Object.defineProperty(event, 'matches', { value: matches })
      target.dispatchEvent(event)
    },
  }
}

describe('resolveScheme', () => {
  it('honours an explicit preference regardless of the OS', () => {
    expect(resolveScheme('light', true)).toBe('light')
    expect(resolveScheme('dark', false)).toBe('dark')
  })

  it('follows the OS for "system"', () => {
    expect(resolveScheme('system', true)).toBe('dark')
    expect(resolveScheme('system', false)).toBe('light')
  })

  it('treats unknown values as "system"', () => {
    expect(normalizePreference('sepia')).toBe('system')
    expect(resolveScheme(undefined, true)).toBe('dark')
  })
})

describe('applyScheme', () => {
  it('sets the dark class, color-scheme and stored preference', () => {
    const root = makeRoot()

    expect(applyScheme(root, 'dark', false)).toBe('dark')
    expect(root.classList.contains('dark')).toBe(true)
    expect(root.style.colorScheme).toBe('dark')
    expect(root.dataset.themePreference).toBe('dark')

    applyScheme(root, 'light', true)
    expect(root.classList.contains('dark')).toBe(false)
    expect(root.style.colorScheme).toBe('light')
  })
})

describe('watchSystemScheme', () => {
  it('re-applies on OS changes only while the preference is "system"', () => {
    const root = makeRoot()
    const mql = makeMediaQuery()
    applyScheme(root, 'system', false)
    const stop = watchSystemScheme(root, mql)

    mql.change(true)
    expect(root.classList.contains('dark')).toBe(true)

    applyScheme(root, 'light', true)
    mql.change(true)
    expect(root.classList.contains('dark')).toBe(false)

    applyScheme(root, 'system', false)
    stop()
    mql.change(true)
    expect(root.classList.contains('dark')).toBe(false)
  })
})
