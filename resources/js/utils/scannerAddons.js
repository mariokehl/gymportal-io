/**
 * The usage add-ons a device settles against.
 *
 * Devices saved before several links existed only carry the single addon
 * relation, so that one is the fallback.
 */
export const linkedAddons = (scanner) => {
  if (scanner?.addons?.length) {
    return scanner.addons
  }

  return scanner?.addon ? [scanner.addon] : []
}

/**
 * Group add-ons by the location selling them: the device's own location
 * first, then the sibling locations by name.
 *
 * @returns {Array<{ gymId: number|null, gymName: string|null, isOwnLocation: boolean, addons: Array }>}
 */
export const groupAddonsByLocation = (addons, ownGymId) => {
  const groups = new Map()

  for (const addon of addons) {
    // An add-on without gym_id comes from the legacy single relation, which
    // only ever pointed at the device's own location.
    const gymId = addon.gym_id ?? ownGymId

    if (!groups.has(gymId)) {
      groups.set(gymId, {
        gymId,
        gymName: addon.gym?.name ?? addon.gym_name ?? null,
        isOwnLocation: gymId === ownGymId,
        addons: [],
      })
    }

    groups.get(gymId).addons.push(addon)
  }

  return [...groups.values()].sort((a, b) => {
    if (a.isOwnLocation !== b.isOwnLocation) {
      return a.isOwnLocation ? -1 : 1
    }

    return (a.gymName ?? '').localeCompare(b.gymName ?? '', 'de')
  })
}
