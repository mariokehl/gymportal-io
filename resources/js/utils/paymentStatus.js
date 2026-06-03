// utils/paymentStatus.js
// Gemeinsame Utilities für Payment-Status, analog zu memberStatus.js

/**
 * Status-Konfiguration mit allen relevanten Daten.
 * Spiegelt die Werte aus App\Models\Payment (status_text / status_color) wider.
 */
export const statusConfig = {
  pending: {
    label: 'Ausstehend',
    color: 'yellow',
    classes: 'bg-yellow-100 text-yellow-800',
    filled: false
  },
  paid: {
    label: 'Bezahlt',
    color: 'green',
    classes: 'bg-green-100 text-green-800',
    filled: true
  },
  completed: {
    label: 'Bezahlt',
    color: 'green',
    classes: 'bg-green-100 text-green-800',
    filled: true
  },
  failed: {
    label: 'Fehlgeschlagen',
    color: 'red',
    classes: 'bg-red-100 text-red-800',
    filled: true
  },
  refunded: {
    label: 'Erstattet',
    color: 'blue',
    classes: 'bg-indigo-100 text-indigo-800',
    filled: true
  },
  partially_refunded: {
    label: 'Teilweise erstattet',
    color: 'blue',
    classes: 'bg-indigo-100 text-indigo-800',
    filled: true
  },
  chargeback: {
    label: 'Rückbuchung',
    color: 'red',
    classes: 'bg-red-100 text-red-800',
    filled: true
  },
  expired: {
    label: 'Verfallen',
    color: 'gray',
    classes: 'bg-gray-100 text-gray-800',
    filled: false
  },
  canceled: {
    label: 'Abgebrochen',
    color: 'red',
    classes: 'bg-red-100 text-red-800',
    filled: true
  },
  unknown: {
    label: 'Unbekannt',
    color: 'gray',
    classes: 'bg-gray-100 text-gray-800',
    filled: false
  }
}

/**
 * Status-Konfiguration für Rückbuchungen (App\Models\Chargeback)
 */
export const chargebackStatusConfig = {
  received: {
    label: 'Eingegangen',
    color: 'red',
    classes: 'bg-red-100 text-red-800',
    filled: true
  },
  accepted: {
    label: 'Akzeptiert',
    color: 'gray',
    classes: 'bg-gray-100 text-gray-800',
    filled: true
  },
  disputed: {
    label: 'Angefochten',
    color: 'yellow',
    classes: 'bg-yellow-100 text-yellow-800',
    filled: false
  },
  reversed: {
    label: 'Rückgängig gemacht',
    color: 'green',
    classes: 'bg-green-100 text-green-800',
    filled: true
  }
}

/**
 * Status-Konfiguration für Erstattungen (App\Models\Refund)
 */
export const refundStatusConfig = {
  pending: {
    label: 'Ausstehend',
    color: 'yellow',
    classes: 'bg-yellow-100 text-yellow-800',
    filled: false
  },
  processing: {
    label: 'In Bearbeitung',
    color: 'blue',
    classes: 'bg-indigo-100 text-indigo-800',
    filled: false
  },
  refunded: {
    label: 'Erstattet',
    color: 'green',
    classes: 'bg-green-100 text-green-800',
    filled: true
  },
  failed: {
    label: 'Fehlgeschlagen',
    color: 'red',
    classes: 'bg-red-100 text-red-800',
    filled: true
  }
}

/**
 * Wählt die passende Konfiguration anhand des Typs (payment | chargeback | refund)
 */
function configFor(type) {
  if (type === 'chargeback') return chargebackStatusConfig
  if (type === 'refund') return refundStatusConfig
  return statusConfig
}

/**
 * Gibt den lokalisierten Text für einen Status zurück
 */
export function getStatusText(status, type = 'payment') {
  return configFor(type)[status]?.label || status
}

/**
 * Gibt die CSS-Klassen für einen Status-Badge zurück
 */
export function getStatusBadgeClass(status, type = 'payment') {
  return configFor(type)[status]?.classes || 'bg-gray-100 text-gray-800'
}

/**
 * Gibt die Farbe für einen Status zurück (für dynamische Styles)
 */
export function getStatusColor(status, type = 'payment') {
  return configFor(type)[status]?.color || 'gray'
}

/**
 * Gibt zurück, ob das Kreis-Icon ausgefüllt dargestellt werden soll
 */
export function isStatusFilled(status, type = 'payment') {
  return configFor(type)[status]?.filled ?? false
}

export default {
  statusConfig,
  chargebackStatusConfig,
  refundStatusConfig,
  getStatusText,
  getStatusBadgeClass,
  getStatusColor,
  isStatusFilled
}
