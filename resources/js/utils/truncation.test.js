import { describe, it, expect } from 'vitest'
import { hasTruncatedText } from '@/utils/truncation'

// Minimal stand-ins for DOM elements: only what the check reads.
const element = ({ truncate = false, scrollWidth = 100, clientWidth = 100, children = [] } = {}) => ({
  classList: { contains: name => truncate && name === 'truncate' },
  scrollWidth,
  clientWidth,
  querySelectorAll: () => children.filter(child => child.classList.contains('truncate')),
})

describe('hasTruncatedText', () => {
  it('detects a truncated descendant', () => {
    const root = element({ children: [element({ truncate: true, scrollWidth: 180, clientWidth: 150 })] })

    expect(hasTruncatedText(root)).toBe(true)
  })

  it('detects a truncated root element', () => {
    expect(hasTruncatedText(element({ truncate: true, scrollWidth: 171, clientWidth: 170 }))).toBe(true)
  })

  it('ignores text that fits and elements without the truncate class', () => {
    const root = element({
      children: [
        element({ truncate: true, scrollWidth: 90, clientWidth: 150 }),
        element({ scrollWidth: 400, clientWidth: 100 }),
      ],
    })

    expect(hasTruncatedText(root)).toBe(false)
    expect(hasTruncatedText(null)).toBe(false)
  })
})
