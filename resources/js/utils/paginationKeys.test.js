import { describe, it, expect } from 'vitest'
import { paginationDirection } from './paginationKeys'

const body = { tagName: 'BODY', parentElement: null, closest: () => null, scrollWidth: 0, clientWidth: 0 }

const node = ({ tagName = 'BUTTON', parentElement = body, closest = () => null, scrollWidth = 0, clientWidth = 0, overflowX = 'visible', ...rest } = {}) => ({
  tagName, parentElement, closest, scrollWidth, clientWidth, overflowX, ...rest,
})

const fakeWindow = ({ openModal = false } = {}) => ({
  document: { body, querySelector: () => (openModal ? {} : null) },
  getComputedStyle: element => ({ overflowX: element.overflowX ?? 'visible' }),
})

const press = (key, { target = body, ...modifiers } = {}) => ({
  key, target, defaultPrevented: false, altKey: false, ctrlKey: false, metaKey: false, shiftKey: false, ...modifiers,
})

describe('paginationDirection', () => {
  it('maps the left and right arrow keys to the previous and next page', () => {
    expect(paginationDirection(press('ArrowLeft'), { win: fakeWindow() })).toBe('prev')
    expect(paginationDirection(press('ArrowRight'), { win: fakeWindow() })).toBe('next')
  })

  it('ignores other keys', () => {
    expect(paginationDirection(press('ArrowDown'), { win: fakeWindow() })).toBeNull()
    expect(paginationDirection(press('Home'), { win: fakeWindow() })).toBeNull()
  })

  it('leaves modifier combinations like Alt+← (browser back) alone', () => {
    for (const modifier of ['altKey', 'ctrlKey', 'metaKey', 'shiftKey']) {
      expect(paginationDirection(press('ArrowLeft', { [modifier]: true }), { win: fakeWindow() })).toBeNull()
    }
  })

  it('ignores presses another handler already took', () => {
    expect(paginationDirection(press('ArrowRight', { defaultPrevented: true }), { win: fakeWindow() })).toBeNull()
  })

  it('keeps the arrow keys for text fields, selects and editable content', () => {
    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT']) {
      expect(paginationDirection(press('ArrowRight', { target: node({ tagName }) }), { win: fakeWindow() })).toBeNull()
    }
    expect(paginationDirection(press('ArrowRight', { target: node({ isContentEditable: true }) }), { win: fakeWindow() })).toBeNull()
  })

  it('keeps the arrow keys for widgets that navigate with them', () => {
    const target = node({ closest: () => ({}) })

    expect(paginationDirection(press('ArrowRight', { target }), { win: fakeWindow() })).toBeNull()
  })

  it('does nothing while a dialog is open', () => {
    expect(paginationDirection(press('ArrowRight'), { win: fakeWindow({ openModal: true }) })).toBeNull()
  })

  it('lets a horizontally overflowing container keep scrolling', () => {
    const scroller = node({ tagName: 'DIV', scrollWidth: 1180, clientWidth: 400, overflowX: 'auto' })
    const target = node({ parentElement: scroller })

    expect(paginationDirection(press('ArrowRight', { target }), { win: fakeWindow() })).toBeNull()
  })

  it('pages inside a scroll container that currently fits', () => {
    const scroller = node({ tagName: 'DIV', scrollWidth: 1180, clientWidth: 1180, overflowX: 'auto' })
    const target = node({ parentElement: scroller })

    expect(paginationDirection(press('ArrowRight', { target }), { win: fakeWindow() })).toBe('next')
  })
})
