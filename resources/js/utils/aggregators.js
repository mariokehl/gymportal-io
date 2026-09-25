// Corporate fitness aggregators. Keys mirror App\Enums\Aggregator; the list
// is static for now and available to every gym.

export const AGGREGATOR_STATUS_PENDING = 'pending'
export const AGGREGATOR_STATUS_LINKED = 'linked'

// Colours are inline values because the badges use brand-like tones that are
// not part of the Tailwind palette in use.
export const aggregators = [
  { key: 'wellpass', name: 'EGYM Wellpass', short: 'WP', bg: '#cffafe', fg: '#155e75', border: '#22d3ee', pendingFg: '#155e75' },
  { key: 'hansefit', name: 'Hansefit', short: 'HF', bg: '#dbeafe', fg: '#1e40af', border: '#60a5fa', pendingFg: '#1e40af' },
  { key: 'usc', name: 'Urban Sports Club', short: 'US', bg: '#111827', fg: '#ffffff', border: '#6b7280', pendingFg: '#111827' },
  { key: 'wellhub', name: 'Wellhub (Gympass)', short: 'WH', bg: '#fce7f3', fg: '#9d174d', border: '#f472b6', pendingFg: '#9d174d' },
]

export const findAggregator = (key) => aggregators.find(aggregator => aggregator.key === key) ?? null

export const aggregatorStatusLabel = (status) => status === AGGREGATOR_STATUS_LINKED
  ? 'Verknüpft'
  : 'Verknüpfung ausstehend'

// Options of the member list filter: all members, any aggregator, none, or
// one specific provider.
export const aggregatorFilterOptions = () => [
  { value: '', label: 'Alle Mitglieder' },
  { value: 'any', label: 'Nur Aggregatoren' },
  { value: 'none', label: 'Ohne Aggregator' },
  ...aggregators.map(aggregator => ({ value: aggregator.key, label: aggregator.name })),
]

// Inline style of the short badge: filled once linked, dashed outline while
// the link is pending.
export const aggregatorBadgeStyle = (aggregator, status) => {
  if (!aggregator) return {}

  if (status === AGGREGATOR_STATUS_LINKED) {
    return { background: aggregator.bg, color: aggregator.fg, border: `1px solid ${aggregator.bg}` }
  }

  return { background: '#ffffff', color: aggregator.pendingFg, border: `1px dashed ${aggregator.border}` }
}
