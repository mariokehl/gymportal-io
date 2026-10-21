import { describe, expect, it } from 'vitest'
import { groupAddonsByLocation, linkedAddons } from './scannerAddons'

describe('linkedAddons', () => {
    it('returns the linked add-ons', () => {
        const addons = [{ id: 1 }, { id: 2 }]

        expect(linkedAddons({ addons, addon: { id: 1 } })).toBe(addons)
    })

    it('falls back to the single legacy relation', () => {
        expect(linkedAddons({ addons: [], addon: { id: 7 } })).toEqual([{ id: 7 }])
    })

    it('is empty for a device without a link', () => {
        expect(linkedAddons({ addons: [], addon: null })).toEqual([])
        expect(linkedAddons(null)).toEqual([])
    })
})

describe('groupAddonsByLocation', () => {
    const berlin = 1

    it('puts the own location first and siblings by name', () => {
        const groups = groupAddonsByLocation([
            { id: 1, gym_id: 3, gym: { name: 'FitZone München' } },
            { id: 2, gym_id: berlin, gym: { name: 'FitZone Berlin' } },
            { id: 3, gym_id: 2, gym: { name: 'FitZone Hamburg' } },
            { id: 4, gym_id: berlin, gym: { name: 'FitZone Berlin' } },
        ], berlin)

        expect(groups.map(group => group.gymName)).toEqual([
            'FitZone Berlin',
            'FitZone Hamburg',
            'FitZone München',
        ])
        expect(groups[0].isOwnLocation).toBe(true)
        expect(groups[0].addons.map(addon => addon.id)).toEqual([2, 4])
        expect(groups[1].isOwnLocation).toBe(false)
    })

    it('reads the location name from a flat gym_name too', () => {
        const [group] = groupAddonsByLocation([{ id: 1, gym_id: 3, gym_name: 'FitZone München' }], berlin)

        expect(group.gymName).toBe('FitZone München')
    })

    it('treats an add-on without gym_id as the own location', () => {
        const [group] = groupAddonsByLocation([{ id: 1, name: 'Getränke' }], berlin)

        expect(group.isOwnLocation).toBe(true)
    })
})
