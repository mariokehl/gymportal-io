import { DoorOpen, LogIn, LogOut, CupSoda, ShieldCheck, ClipboardCheck } from 'lucide-vue-next'

/**
 * The job a device does. Mirrors App\Models\GymScanner::DEVICE_TASKS.
 */
export const DEVICE_TASKS = [
  {
    value: 'checkin',
    label: 'Automatischer Check-in',
    icon: LogIn,
    hint: 'Kontrolliert den Einlass und erfasst den Check-in. Der Check-out erfolgt softwareseitig.',
  },
  {
    value: 'checkin_checkout',
    label: 'Automatischer Check-in/out',
    icon: DoorOpen,
    hint: 'Prüft, ob das Mitglied ins Studio darf, und führt Check-in oder Check-out aus. Nur sinnvoll, wenn das Gerät in beide Richtungen gescannt wird.',
  },
  {
    value: 'checkout',
    label: 'Automatischer Check-out',
    icon: LogOut,
    hint: 'Kontrolliert den Ausgang und trägt Mitglieder aus.',
  },
  {
    value: 'dispenser',
    label: 'Getränkeanlage (Zapfstelle)',
    shortLabel: 'Getränkeanlage',
    icon: CupSoda,
    hint: 'Verbindet eine Getränkeanlage. Prüft das Leistungskontingent und schaltet den Zapfvorgang frei.',
  },
  {
    value: 'area_control',
    label: 'Automatische Bereichskontrolle',
    shortLabel: 'Bereichskontrolle',
    icon: ShieldCheck,
    hint: 'Beschränkt Bereiche (z.B. Sauna) auf Basis gebuchter Leistungen.',
  },
  {
    value: 'manual',
    label: 'Manueller Check-in/out',
    icon: ClipboardCheck,
    hint: 'Zeigt am Tresen einen Check-in-Dialog zur manuellen Freigabe.',
  },
]

/**
 * Tasks that settle against the quota of a linked usage add-on.
 * Mirrors App\Models\GymScanner::ADDON_LINKED_TASKS.
 */
export const ADDON_LINKED_TASKS = ['dispenser', 'area_control']

/**
 * Preselected task for a new device. Mirrors the column default in the
 * database: most gyms run a single scanner at the entrance and handle the
 * check-out in software.
 */
export const DEFAULT_DEVICE_TASK = 'checkin'

export function findDeviceTask(value) {
  return DEVICE_TASKS.find(task => task.value === value) ?? null
}

/**
 * Compact label for tables and badges, falling back to the full one.
 */
export function deviceTaskLabel(value) {
  const task = findDeviceTask(value)

  return task ? (task.shortLabel ?? task.label) : '—'
}

export function deviceTaskIcon(value) {
  return findDeviceTask(value)?.icon ?? null
}

export function deviceTaskHint(value) {
  return findDeviceTask(value)?.hint ?? ''
}

export function isAddonLinkedTask(value) {
  return ADDON_LINKED_TASKS.includes(value)
}
