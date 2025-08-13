// utils/memberStatus.js
// Gemeinsame Utilities für Member-Status, die in mehreren Komponenten verwendet werden

import {
  UserCheck, UserX, Pause, Clock, AlertTriangle
} from 'lucide-vue-next'

/**
 * Status-Konfiguration mit allen relevanten Daten
 */
export const statusConfig = {
  active: {
    label: 'Aktiv',
    color: 'green',
    classes: 'bg-green-100 text-green-700',
    icon: UserCheck,
    description: 'Vollzugriff auf alle Dienste'
  },
  inactive: {
    label: 'Inaktiv',
    color: 'gray',
    classes: 'bg-gray-100 text-gray-700',
    icon: UserX,
    description: 'Kein Zugriff, keine laufenden Kosten'
  },
  paused: {
    label: 'Pausiert',
    color: 'yellow',
    classes: 'bg-yellow-100 text-yellow-700',
    icon: Pause,
    description: 'Temporär ausgesetzt'
  },
  pending: {
    label: 'Ausstehend',
    color: 'orange',
    classes: 'bg-orange-100 text-orange-700',
    icon: Clock,
    description: 'Wartet auf Aktivierung'
  },
  overdue: {
    label: 'Überfällig',
    color: 'red',
    classes: 'bg-red-100 text-red-700',
    icon: AlertTriangle,
    description: 'Zahlungen ausstehend'
  }
}

/**
 * Gibt den lokalisierten Text für einen Status zurück
 */
export function getStatusText(status) {
  return statusConfig[status]?.label || status
}

/**
 * Gibt die CSS-Klassen für einen Status-Badge zurück
 */
export function getStatusBadgeClass(status) {
  return statusConfig[status]?.classes || 'bg-gray-100 text-gray-700'
}

/**
 * Gibt das Icon für einen Status zurück
 */
export function getStatusIcon(status) {
  return statusConfig[status]?.icon || null
}

/**
 * Gibt die Farbe für einen Status zurück (für dynamische Styles)
 */
export function getStatusColor(status) {
  return statusConfig[status]?.color || 'gray'
}

/**
 * Gibt die Beschreibung für einen Status zurück
 */
export function getStatusDescription(status) {
  return statusConfig[status]?.description || ''
}

/**
 * Formatiert ein Datum im deutschen Format
 */
export function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

/**
 * Formatiert ein Datum mit Uhrzeit im deutschen Format
 */
export function formatDateTime(datetime) {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Formatiert eine Uhrzeit
 */
export function formatTime(datetime) {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('de-DE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Gibt einen relativen Zeitstring zurück (z.B. "vor 2 Tagen")
 */
export function formatRelativeTime(date) {
  if (!date) return ''

  const now = new Date()
  const past = new Date(date)
  const diffInSeconds = Math.floor((now - past) / 1000)

  if (diffInSeconds < 60) return 'gerade eben'
  if (diffInSeconds < 3600) return `vor ${Math.floor(diffInSeconds / 60)} Minuten`
  if (diffInSeconds < 86400) return `vor ${Math.floor(diffInSeconds / 3600)} Stunden`
  if (diffInSeconds < 2592000) return `vor ${Math.floor(diffInSeconds / 86400)} Tagen`
  if (diffInSeconds < 31536000) return `vor ${Math.floor(diffInSeconds / 2592000)} Monaten`
  return `vor ${Math.floor(diffInSeconds / 31536000)} Jahren`
}

/**
 * Validiert ob ein Status-Übergang erlaubt ist
 */
export function canTransitionStatus(fromStatus, toStatus, memberData = {}) {
  // Gleicher Status ist nicht erlaubt
  if (fromStatus === toStatus) return false

  // Spezifische Validierungen
  if (toStatus === 'inactive') {
    // Prüfe auf aktive Mitgliedschaften
    if (memberData.hasActiveMembership) return false
    // Prüfe auf ausstehende Zahlungen
    if (memberData.hasPendingPayments) return false
  }

  if (toStatus === 'active' && fromStatus === 'pending') {
    // Prüfe SEPA-Mandat und Zahlungsmethode
    if (memberData.needsSepaMandate) return false
    if (!memberData.hasActivePaymentMethod) return false
  }

  // Weitere Validierungen...

  return true
}

/**
 * Gibt alle möglichen Status-Optionen zurück
 */
export function getAllStatusOptions() {
  return Object.entries(statusConfig).map(([value, config]) => ({
    value,
    ...config
  }))
}

/**
 * Export als Default-Objekt für einfachen Import
 */
export default {
  statusConfig,
  getStatusText,
  getStatusBadgeClass,
  getStatusIcon,
  getStatusColor,
  getStatusDescription,
  formatDate,
  formatDateTime,
  formatTime,
  formatRelativeTime,
  canTransitionStatus,
  getAllStatusOptions
}
