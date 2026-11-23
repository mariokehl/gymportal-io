import { describe, it, expect, vi } from 'vitest'
import { focusListIfPaginating, scrollListIntoView } from './listScroll'

const fakeWindow = ({ scrollMarginTop = '96px' } = {}) => ({
  getComputedStyle: () => ({ scrollMarginTop }),
})

const fakeElement = top => ({
  getBoundingClientRect: () => ({ top }),
  scrollIntoView: vi.fn(),
})

describe('scrollListIntoView', () => {
  // Instant, so Inertia's scroll preservation captures the final position
  // instead of cutting off a running smooth scroll.
  it('jumps instantly when the list top is scrolled out of view', () => {
    const element = fakeElement(-800)

    expect(scrollListIntoView(element, { win: fakeWindow() })).toBe(true)
    expect(element.scrollIntoView).toHaveBeenCalledWith({ block: 'start', behavior: 'instant' })
  })

  it('treats a list top hidden under the sticky header as out of view', () => {
    const element = fakeElement(40)

    expect(scrollListIntoView(element, { win: fakeWindow() })).toBe(true)
  })

  it('does not scroll when the list top is visible', () => {
    const element = fakeElement(200)

    expect(scrollListIntoView(element, { win: fakeWindow() })).toBe(false)
    expect(element.scrollIntoView).not.toHaveBeenCalled()
  })

  it('ignores a missing element', () => {
    expect(scrollListIntoView(null, { win: fakeWindow() })).toBe(false)
  })
})

describe('focusListIfPaginating', () => {
  const button = {}
  const pagination = { contains: target => target === button }

  it('moves focus to the list when a pagination button was focused', () => {
    const list = { focus: vi.fn() }

    expect(focusListIfPaginating(list, pagination, { doc: { activeElement: button } })).toBe(true)
    expect(list.focus).toHaveBeenCalledWith({ preventScroll: true })
  })

  it('leaves focus alone when it is elsewhere', () => {
    const list = { focus: vi.fn() }

    expect(focusListIfPaginating(list, pagination, { doc: { activeElement: {} } })).toBe(false)
    expect(list.focus).not.toHaveBeenCalled()
  })
})
