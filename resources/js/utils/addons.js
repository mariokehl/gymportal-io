/**
 * Determine which add-on ids should be submitted for a chosen plan.
 *
 * Included add-ons are preselected and not deselectable, so they are always
 * part of the result. Optional add-ons are only included when the customer
 * checked them. This mirrors the checkbox state collected from the widget DOM
 * and is kept here as a pure function so it can be unit-tested.
 *
 * @param {Array<{id: number, mode: 'included'|'optional', checked?: boolean}>} addons
 * @returns {number[]} the selected add-on ids
 */
export function resolveSelectedAddonIds(addons) {
  if (!Array.isArray(addons)) {
    return []
  }

  return addons
    .filter((addon) => addon.mode === 'included' || addon.checked === true)
    .map((addon) => addon.id)
}
