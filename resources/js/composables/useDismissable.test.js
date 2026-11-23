import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useDismissable } from './useDismissable'

// Vitest runs in node: document is a plain EventTarget and elements are stubs
// with just the contains()/focus() API the composable touches.
const element = (children = []) => ({
  contains: (target) => target === element || children.includes(target),
  focused: false,
  focus() { this.focused = true },
})

const dispatch = (type, props = {}) => {
  const event = new Event(type)
  // Event#target is a read-only getter; shadow it with an own property.
  for (const [key, value] of Object.entries(props)) {
    Object.defineProperty(event, key, { value })
  }
  document.dispatchEvent(event)
}

let scope

const setup = (initiallyOpen = true) => {
  const inside = {}
  const root = element([inside])
  const trigger = element()
  const open = ref(initiallyOpen)
  scope = effectScope()
  scope.run(() => useDismissable(ref(root), open, { triggerRef: ref(trigger) }))
  return { open, inside, trigger }
}

beforeEach(() => {
  globalThis.document = new EventTarget()
})

afterEach(() => {
  scope?.stop()
  delete globalThis.document
})

describe('useDismissable', () => {
  it('closes on a pointer press outside the root', () => {
    const { open } = setup()

    dispatch('pointerdown', { target: {} })

    expect(open.value).toBe(false)
  })

  it('stays open on a pointer press inside the root', () => {
    const { open, inside } = setup()

    dispatch('pointerdown', { target: inside })

    expect(open.value).toBe(true)
  })

  it('closes on Escape and returns focus to the trigger', () => {
    const { open, trigger } = setup()

    dispatch('keydown', { key: 'Escape' })

    expect(open.value).toBe(false)
    expect(trigger.focused).toBe(true)
  })

  it('ignores other keys', () => {
    const { open } = setup()

    dispatch('keydown', { key: 'Enter' })

    expect(open.value).toBe(true)
  })

  it('closes after an Inertia visit without moving focus', () => {
    const { open, trigger } = setup()

    dispatch('inertia:navigate')

    expect(open.value).toBe(false)
    expect(trigger.focused).toBe(false)
  })

  it('only listens for outside presses while open', async () => {
    const { open, trigger } = setup(false)

    dispatch('keydown', { key: 'Escape' })
    expect(trigger.focused).toBe(false)

    open.value = true
    await nextTick()
    dispatch('pointerdown', { target: {} })

    expect(open.value).toBe(false)
  })

  it('stops listening once its scope is disposed', () => {
    const { open } = setup()
    scope.stop()

    dispatch('inertia:navigate')

    expect(open.value).toBe(true)
  })
})
