import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { BOTTOM_BAR_HEIGHT_VAR, useBottomBarOffset } from './useBottomBarOffset'

// Vitest runs in node, so <html> is stubbed with just the style API the
// composable touches.
const styles = new Map()

beforeEach(() => {
  styles.clear()
  globalThis.document = {
    documentElement: {
      style: {
        setProperty: (name, value) => styles.set(name, value),
        removeProperty: (name) => styles.delete(name),
      },
    },
  }
})

afterEach(() => {
  delete globalThis.document
})

const run = (barRef) => {
  const scope = effectScope()
  scope.run(() => useBottomBarOffset(barRef))
  return scope
}

describe('useBottomBarOffset', () => {
  it('publishes the height of a rendered bar', () => {
    run(ref({ offsetHeight: 78 }))

    expect(styles.get(BOTTOM_BAR_HEIGHT_VAR)).toBe('78px')
  })

  it('does not publish anything while the bar is not rendered', () => {
    run(ref(null))

    expect(styles.has(BOTTOM_BAR_HEIGHT_VAR)).toBe(false)
  })

  it('removes the offset once the bar disappears', async () => {
    const bar = ref({ offsetHeight: 78 })
    run(bar)

    bar.value = null
    await nextTick()

    expect(styles.has(BOTTOM_BAR_HEIGHT_VAR)).toBe(false)
  })

  it('removes the offset when the owning component goes away', () => {
    const scope = run(ref({ offsetHeight: 78 }))

    scope.stop()

    expect(styles.has(BOTTOM_BAR_HEIGHT_VAR)).toBe(false)
  })
})
