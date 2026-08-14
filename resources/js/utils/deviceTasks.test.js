import { describe, it, expect } from 'vitest'
import {
  DEVICE_TASKS,
  ADDON_LINKED_TASKS,
  DEFAULT_DEVICE_TASK,
  findDeviceTask,
  deviceTaskLabel,
  deviceTaskIcon,
  deviceTaskHint,
  isAddonLinkedTask,
} from './deviceTasks'

describe('DEVICE_TASKS', () => {
  it('covers the six tasks supported by the backend', () => {
    expect(DEVICE_TASKS.map(task => task.value)).toEqual([
      'checkin',
      'checkin_checkout',
      'checkout',
      'dispenser',
      'area_control',
      'manual',
    ])
  })

  it('gives every task a label, an icon and a hint', () => {
    DEVICE_TASKS.forEach(task => {
      expect(task.label).toBeTruthy()
      expect(task.icon).toBeTruthy()
      expect(task.hint).toBeTruthy()
    })
  })

  it('defaults to plain check-in and offers it first', () => {
    expect(DEFAULT_DEVICE_TASK).toBe('checkin')
    expect(findDeviceTask(DEFAULT_DEVICE_TASK)).not.toBeNull()
    expect(DEVICE_TASKS[0].value).toBe(DEFAULT_DEVICE_TASK)
  })
})

describe('deviceTaskLabel', () => {
  it('prefers the short label where one exists', () => {
    expect(deviceTaskLabel('dispenser')).toBe('Getränkeanlage')
    expect(deviceTaskLabel('area_control')).toBe('Bereichskontrolle')
  })

  it('falls back to the full label', () => {
    expect(deviceTaskLabel('checkin')).toBe('Automatischer Check-in')
  })

  it('renders a dash for an unknown task', () => {
    expect(deviceTaskLabel('teleporter')).toBe('—')
    expect(deviceTaskLabel(null)).toBe('—')
  })
})

describe('deviceTaskIcon', () => {
  it('returns the icon component of a known task', () => {
    expect(deviceTaskIcon('dispenser')).toBe(findDeviceTask('dispenser').icon)
  })

  it('returns null for an unknown task', () => {
    expect(deviceTaskIcon('teleporter')).toBeNull()
  })
})

describe('deviceTaskHint', () => {
  it('describes what the task does', () => {
    expect(deviceTaskHint('dispenser')).toContain('Getränkeanlage')
  })

  it('returns an empty string for an unknown task', () => {
    expect(deviceTaskHint('teleporter')).toBe('')
  })
})

describe('isAddonLinkedTask', () => {
  it('marks dispenser and area control as add-on linked', () => {
    expect(ADDON_LINKED_TASKS).toEqual(['dispenser', 'area_control'])
    expect(isAddonLinkedTask('dispenser')).toBe(true)
    expect(isAddonLinkedTask('area_control')).toBe(true)
  })

  it('leaves the check-in tasks unlinked', () => {
    expect(isAddonLinkedTask('checkin_checkout')).toBe(false)
    expect(isAddonLinkedTask('checkin')).toBe(false)
    expect(isAddonLinkedTask('checkout')).toBe(false)
    expect(isAddonLinkedTask('manual')).toBe(false)
  })
})
