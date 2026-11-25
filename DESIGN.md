---
name: gymportal.io Backend
description: Membership management for gyms – calm, precise and dependable at the front desk.
colors:
  canvas: "oklch(96.7% 0.003 264.542)"
  surface: "#ffffff"
  surface-muted: "oklch(98.5% 0.002 247.839)"
  inverse: "oklch(21% 0.034 264.665)"
  fill: "oklch(96.7% 0.003 264.542)"
  fill-strong: "oklch(92.8% 0.006 264.531)"
  line-subtle: "oklch(96.7% 0.003 264.542)"
  line: "oklch(92.8% 0.006 264.531)"
  line-strong: "oklch(87.2% 0.01 258.338)"
  ink: "oklch(21% 0.034 264.665)"
  ink-secondary: "oklch(37.3% 0.034 259.733)"
  ink-muted: "oklch(52% 0.027 264.4)"
  ink-disabled: "oklch(70.7% 0.022 261.325)"
  brand: "oklch(51.1% 0.262 276.966)"
  brand-strong: "oklch(45.7% 0.24 277.023)"
  brand-subtle: "oklch(96.2% 0.018 272.314)"
  brand-soft: "oklch(93% 0.034 272.788)"
  brand-line: "oklch(87% 0.065 274.039)"
  brand-ink: "oklch(51.1% 0.262 276.966)"
  brand-ink-strong: "oklch(45.7% 0.24 277.023)"
  focus: "oklch(58.5% 0.233 277.117)"
  guest-solid: "oklch(55.3% 0.195 38.402)"
  success: "oklch(52.7% 0.154 150.069)"
  success-strong: "oklch(44.8% 0.119 151.328)"
  success-subtle: "oklch(98.2% 0.018 155.826)"
  success-soft: "oklch(96.2% 0.044 156.743)"
  success-line: "oklch(92.5% 0.084 155.995)"
  success-solid: "oklch(62.7% 0.194 149.214)"
  warning: "oklch(55.5% 0.163 48.998)"
  warning-strong: "oklch(47.3% 0.137 46.201)"
  warning-subtle: "oklch(98.7% 0.022 95.277)"
  warning-soft: "oklch(96.2% 0.059 95.617)"
  warning-line: "oklch(92.4% 0.12 95.746)"
  warning-solid: "oklch(76.9% 0.188 70.08)"
  danger: "oklch(50.5% 0.213 27.518)"
  danger-strong: "oklch(44.4% 0.177 26.899)"
  danger-subtle: "oklch(97.1% 0.013 17.38)"
  danger-soft: "oklch(93.6% 0.032 17.717)"
  danger-line: "oklch(88.5% 0.062 18.334)"
  danger-solid: "oklch(57.7% 0.245 27.325)"
  info: "oklch(48.8% 0.243 264.376)"
  info-strong: "oklch(42.4% 0.199 265.638)"
  info-subtle: "oklch(97% 0.014 254.604)"
  info-soft: "oklch(93.2% 0.032 255.585)"
  info-line: "oklch(88.2% 0.059 254.128)"
  info-solid: "oklch(54.6% 0.245 262.881)"
  dark-canvas: "#0f1218"
  dark-surface: "#161a21"
  dark-surface-muted: "#1b2029"
  dark-fill: "#1e232c"
  dark-fill-strong: "#272d37"
  dark-inverse: "#2b313c"
  dark-ink: "#eef0f4"
  dark-ink-secondary: "#c5cad3"
  dark-ink-muted: "#9aa1ae"
  dark-ink-disabled: "#6b7280"
  dark-brand-ink: "oklch(78.5% 0.115 274.713)"
  dark-brand-ink-strong: "oklch(87% 0.065 274.039)"
  dark-focus: "oklch(67.3% 0.182 276.935)"
  logo-violet: "#8b5cf6"
  logo-cyan: "#06b6d4"
typography:
  headline:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "-0.011em"
  title:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.55
    letterSpacing: "-0.011em"
  subtitle:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
  figure:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.33
    fontFeature: "tnum"
  body:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  compact:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.38
  label:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.33
  code:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  micro:
    fontFamily: "Archivo, 'Archivo Fallback', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.6875rem"
    fontWeight: 600
    lineHeight: 1.45
rounded:
  sm: "0.25rem"
  md: "0.375rem"
  lg: "0.5rem"
  full: "9999px"
spacing:
  "1": "0.25rem"
  "2": "0.5rem"
  "3": "0.75rem"
  "4": "1rem"
  "6": "1.5rem"
components:
  button-primary:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.surface}"
    typography: "{typography.body}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  button-primary-hover:
    backgroundColor: "{colors.brand-strong}"
    textColor: "{colors.surface}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-secondary}"
    typography: "{typography.body}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  button-secondary-hover:
    backgroundColor: "{colors.surface-muted}"
  button-danger:
    backgroundColor: "{colors.danger-solid}"
    textColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.5rem 0.75rem"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "1.5rem"
  badge-success:
    backgroundColor: "{colors.success-soft}"
    textColor: "{colors.success}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "0.125rem 0.625rem"
  badge-warning:
    backgroundColor: "{colors.warning-soft}"
    textColor: "{colors.warning}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "0.125rem 0.625rem"
  badge-neutral:
    backgroundColor: "{colors.fill}"
    textColor: "{colors.ink-secondary}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "0.125rem 0.625rem"
  nav-item:
    textColor: "{colors.ink-secondary}"
    typography: "{typography.body}"
    padding: "0.75rem 1rem"
  nav-item-active:
    backgroundColor: "{colors.brand-subtle}"
    textColor: "{colors.brand}"
---

# Design System: gymportal.io Backend

## Overview

**Creative North Star: "The Front Desk"**

At the front desk of a well-run gym everything is within reach and nothing
distracts from the next member. The backend works the same way: an owner finds
what they need in seconds and gets it done by the shortest path, whether at the
reception computer with mouse and keyboard or on the phone on the go. The
interface is calm and dense; it steps back behind members, contracts and
figures.

The brand shows in precision, not decoration: figures sit in clean columns,
states always have the same shape and color, focus and selection are
unambiguous. Indigo is the one color for action and selection; everything else
is neutral or carries a status meaning. Archivo, the typeface shared with the
landing page, runs at normal width in small, fixed steps.

Depth comes from quiet layering: a gray page, white surfaces with a barely
visible shadow, and only what truly floats is lifted.

**Key Characteristics:**
- Calm, precise, dependable; operating mode over expression.
- One action color (indigo), status through fixed roles, no decorative colors.
- Fixed, dense type steps; tabular figures wherever numbers appear.
- Flat surfaces; shadows only to express a layer.
- Search first: member search is always reachable (sidebar, `/`, mobile header).
- Light and dark are equal themes; everyone chooses Light, Dark or Automatic in
  their profile.
- WCAG 2.1 AA as the floor, in both themes, including touch and keyboard.

## Colors

Cool neutral gray as the stage, indigo as the only voice for action, plus four
status tones with fixed roles. Components use roles only, never palette shades.

### Primary
- **Desk Indigo** (brand): primary buttons, active navigation surface,
  selection. Sits between the logo's violet and cyan.
- **Deep Desk Indigo** (brand-strong): hover and pressed state of everything
  filled with brand.
- **Indigo Haze** (brand-subtle / brand-soft / brand-line): background for
  selection and active navigation, brand badges, borders around highlighted
  areas.
- **Desk Indigo as text** (brand-ink / brand-ink-strong): links, active
  navigation labels, indigo text on brand-subtle. Identical to brand in light;
  lighter in dark, where the brand fill would be too dark as text.
- **Focus Indigo** (focus): keyboard focus outlines and field focus only.

### Neutral
- **Hall Gray** (canvas): the page behind all cards.
- **Chalk White** (surface): cards, fields, popovers, header, sidebar.
- **Mist White** (surface-muted): table headers, hover on white rows,
  read-only boxes.
- **Rubber Mat** (fill / fill-strong): neutral badges, hover tints, disabled
  fields, progress tracks.
- **Chalk Line** (line-subtle / line / line-strong): dividers inside cards,
  card borders, field borders.
- **Ink** (ink / ink-secondary / ink-muted / ink-disabled): text in four steps.
  ink-muted is the lightest readable step and holds 4.5:1 on the gray page as
  well; ink-disabled is for disabled controls and decoration only.
- **Night Gray** (inverse): tooltips and dark code surfaces.

### Status
- **success / warning / danger / info**, each with `-strong` (text on badges,
  headings in notice boxes), `-subtle` (notice surface), `-soft` (badge fill),
  `-line` (border on a tinted surface) and `-solid` (filled buttons, dots).
  warning is based on amber.
- **guest-solid**: the filled guest avatar; the same orange in both themes so
  white initials stay readable.
- Category colors without a role (orange for pending members and guests,
  purple for admin, teal, rose) deliberately stay raw palette.

### Dark Theme: "Late Shift"
Cool graphite with the same faint blue cast as the light gray ramp. The page
(dark-canvas) and cards (dark-surface) differ by one step; lines are white at
6–18 % opacity. Text comes in four steps (dark-ink to dark-ink-disabled),
indigo as text in dark-brand-ink. Filled surfaces (brand, `*-solid`,
guest-solid) keep their light value so white labels stay readable; status text
steps one shade lighter, tinted surfaces become 8–16 % washes over the card.
Category colors (orange, purple, teal, rose …) are flipped in dark through CSS
variables: light shades become washes, dark shades become light.

Implementation: roles are runtime variables (`--canvas`, `--ink`, …) with
values under `:root` and `.dark`. The server sets the `dark` class from the
profile preference; "Automatic" is resolved by an inline script before the
first paint.

### Named Rules
**The One Voice Rule.** Indigo means "act here" or "selected". No
indigo-colored headings, icons or surfaces without that meaning.

**The Role-Not-Shade Rule.** Components use `text-ink-muted`,
`bg-success-soft`, `border-line` and so on, never `text-gray-500` or
`bg-green-100`. A new meaning needs a new role in `app.css`, not a palette
value in markup.

**The Ink-Not-Fill Rule.** Indigo as text is always `text-brand-ink`, never
`text-brand`; `brand` is for fills, borders and rings.

**The Readable-Muted Rule.** Readable text is never lighter than ink-muted.
Muted states come from color and context, never from `opacity` on text.

## Typography

**Display Font:** Archivo (with a metric-matched Arial fallback)
**Body Font:** Archivo
**Label/Mono Font:** system monospace for code and keys

**Character:** A sober grotesque with open forms that stays clear at 13 px and
carries dense tables. The backend runs it at normal width; the condensed cuts
belong to the landing page.

### Hierarchy
- **Headline** (600, 20px, 1.4): page title in the app header.
- **Title** (600, 18px, 1.55): section headings in cards.
- **Subtitle** (600, 16px, 1.5): sub-sections in forms ("Adresse",
  "Notfallkontakt").
- **Figure** (700, 24px, tabular figures): key figures on dashboard, finances,
  access control. Key figures are values, not headings.
- **Body** (400, 14px, 1.43): UI text, fields (with a mouse), buttons.
- **Compact** (400, 13px): table cells, filters, toolbars.
- **Label** (500, 12px): meta, help text, badges.
- **Micro** (600, 11px): small chips and counters; the floor.
- **Code** (system monospace, 14px): embed code, API keys, transaction IDs. No
  loaded code font.

### Named Rules
**The Fixed-Steps Rule.** These steps only. No one-off `text-[13px]` values;
`text-compact` and `text-2xs` exist for that.

**The Steady-Figures Rule.** Wherever numbers stack or change live,
`tabular-nums` applies (tables automatically, key figures explicitly).

**The Touch-Sixteen Rule.** Form fields are 14 px with a mouse and 16 px on
touch devices, so iOS does not zoom into them.

## Layout

Persistent app shell: a fixed 256 px sidebar from 1024 px, below that a modal
menu from the left. The header with page title, search, notifications and
profile sticks to the top of the scrolling content area. Content sits on the
gray page with 16 px (phone) or 24 px padding.

Spacing follows a 4 px grid; most common are 8, 16, 12 and 24 px. Groups inside
a card sit close together; cards are 24 px apart.

Responsive decisions follow container width, not only the viewport: lists
with wide tables switch to a compact row list via container query (below a
672 px card width). Key figures run in four columns from 1280 px, two below.
Small controls get a 44 px hit area on touch devices without changing the
desktop layout.

## Elevation & Depth

Quiet layering: the page is hall gray, content sits on white surfaces with a
barely visible shadow; whatever floats above content (popover, dropdown,
toast, menu) is clearly lifted. Shadows keep the Tailwind v3 look the
interface was built with.

### Shadow Vocabulary
- **Resting** (`0 1px 2px 0 rgb(0 0 0 / 0.05)`): cards and header.
- **Floating** (Tailwind `shadow-lg`): popovers, dropdowns, toasts.
- **Drawer** (Tailwind `shadow-2xl`): the open mobile menu, only while open.
- **In dark:** resting becomes a hairline (`0 0 0 1px` line-subtle); floating
  and drawer become a hairline plus a deep shadow
  (`0 16px 40px rgb(0 0 0 / 0.5)`). Depth there comes from surface steps.

### Named Rules
**The Shadow-Means-Layer Rule.** A shadow says "this lies on top of
something". No shadow as a hover effect on cards, no glow, no shadow rings.

## Shapes

Gently rounded and restrained: cards and buttons with an 8 px radius
(`rounded-lg`), form fields with 6 px (`rounded-md`), badges, avatars and pill
tabs fully round. Borders are 1 px in the line roles; rings and outlines are
for focus only.

## Components

### Buttons
- **Shape:** gently rounded (8px).
- **Primary:** brand with white text, 14 px medium, 8/16 px padding; hover
  brand-strong. 44 px tall on touch devices.
- **Secondary:** surface with a line-strong border and ink-secondary text;
  hover surface-muted.
- **Danger:** danger-solid with white text, for final or destructive actions
  only (revoking, regenerating keys).
- **Focus:** no custom rings; the global focus outline applies (2 px focus,
  2 px offset, keyboard focus only).
- **Disabled:** `opacity-50` and `cursor-not-allowed` on the button itself.

### Chips / Status Badges
- **Style:** fully round, `-soft` fill with status text, 12 px semibold, often
  with a Lucide icon. Member status comes from `resources/js/utils/memberStatus.js`.
- **State:** color never carries the meaning alone; the status word is always
  there.

### Cards / Containers
- **Corner Style:** 8px.
- **Background:** surface on canvas.
- **Shadow Strategy:** resting (see Elevation).
- **Border:** optional line when several cards sit close together.
- **Internal Padding:** 24 px (desktop), 16 px in lists.

### Inputs / Fields
- **Style:** one class for every text, select and multi-line field: `.field`
  (1 px line-strong, 6 px radius, surface, placeholder ink-muted). Width and
  padding stay in markup.
- **Focus:** brand border plus a 1 px brand ring – a 2 px edge without layout
  shift.
- **Error / Disabled:** errors via `aria-invalid="true"` (danger border and
  ring); disabled and read-only on fill with ink-muted.

### Navigation
- **Sidebar:** entries 14 px medium in ink-secondary; active entry on
  brand-subtle with brand-ink text and a 2 px brand edge on the right;
  `aria-current="page"`. Locked entries in ink-disabled with a lock and an
  explanation.
- **Tab rail:** pills, active brand with white text, otherwise surface with a
  border; scrolls horizontally on narrow screens.
- **Mobile:** the hamburger opens a modal menu (focus trap, Escape, inert while
  closed); member search has its own button in the header.

### Status Dot (signature)
An 8 px dot in the status role (`bg-success-solid`, `bg-brand`,
`bg-danger-solid`) in front of a quiet line of text, for everything that is
live: subscription status in the sidebar footer, scanner online/offline, live
log. Instead of colored cards with border and icon.

### Appearance (profile)
Three option tiles as real radio buttons (Light, Dark, Automatic) with icon,
title and one line of explanation; the selected tile has a brand border on
brand-subtle. A change applies immediately and is saved in the background.

### Member Search (signature component)
A combobox in the sidebar, reachable with `/`. Shows recently viewed members
first, then results with avatar, member number and status; the active row is
tinted brand-subtle. On the phone the search button opens the menu with the
field focused.

## Do's and Don'ts

### Do:
- **Do** use the role classes from `app.css` (`bg-surface`, `text-ink-muted`,
  `bg-brand`, `bg-success-soft`). In custom CSS use the runtime variables
  (`var(--surface)`, `var(--ink-muted)`) or `--theme(--color-…)`, so both
  themes apply.
- **Do** attach every new page to the persistent shell with
  `defineOptions({ layout: AppShell })` and render its content in `AppLayout`.
- **Do** build form fields with `.field` and link every visible label via
  `for`/`id`; fields without a visible label get an `aria-label`.
- **Do** give small icon buttons `touch-target relative` and a name; space
  neighboring icons further apart on touch.
- **Do** switch wide tables to a row list below a 672 px container width
  (`@container`, `@2xl:`).
- **Do** mark up key figures as values (`<p>`) with `tabular-nums`, not as
  headings; key-figure cards carry label, value and comparison, no icon tiles.
- **Do** check every new surface in light and dark (axe A + AA in both).

### Don't:
- **Don't** write palette shades such as `text-gray-400`, `bg-indigo-600` or
  `text-[13px]` into new markup.
- **Don't** add custom `focus:ring-*` or `focus:outline-*` combinations to
  buttons and links; the global focus outline is enough.
- **Don't** dim text with `opacity`; muted states only through ink-muted,
  surface and icon.
- **Don't** use emoji as icons or warning signs; icons come from Lucide.
- **Don't** use gradients on surfaces, text, buttons or avatars; the only
  gradient is the logo.
- **Don't** set section or group labels in tracked capitals; use sentence
  case. (Table headers may stay small and tracked.)
- **Don't** hard-code hex colors, `bg-white` or `text-black` in components;
  they do not follow the dark theme.
- **Don't** put colored side bars on rounded cards (`border-l-4`); use a full
  border in the `-line` role for notices.
