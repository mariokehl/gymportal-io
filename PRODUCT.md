# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Primary: owners of independent gyms** who manage members, contracts and
payments themselves. They work at the front desk during opening hours, from the
office and on the go on their phone (looking up a member, check-ins, payment
status). Staff and trainers work alongside them with restricted permissions.

Roles in the system: Admin (platform), Owner, Admin (gym), Staff, Trainer,
Member.

**Other audiences outside the backend:**
- Members sign contracts online through the website widget and use the member
  PWA or the branded app.
- Guests buy day passes and multi-visit cards through the guest portal.

## Product Purpose

gymportal.io is a multi-tenant SaaS membership management system for gyms. It
covers the path from signing a contract online through payment (Mollie, SEPA
direct debit) to access (QR/NFC check-in), without the gym having to stitch
systems together.

Success means a gym owner runs their administration without media breaks and
at predictable cost, and their members sign up online without paper.

## Positioning

Four traits set gymportal apart from established gym software:

1. **Open source (GNU GPLv3):** the code is public; gyms can see it and adapt it
   to their own workflows.
2. **Fixed, low price:** €29.00 (excl. VAT) per month and gym, no contract
   lock-in, 30-day free trial.
3. **Online signup built in:** website widget with Mollie payment; the contract
   comes into being without a media break.
4. **Own access control:** QR/NFC check-in with the gym's own scanner hardware
   (Raspberry Pi) instead of an expensive third-party system.

## Operating Context

- **Front desk:** fast member search (search with `/`), following check-ins
  live, checking status and open items. Desktop with mouse and keyboard.
- **On the go:** the same tasks on the phone (touch, narrow layout).
- **Office:** maintaining contracts and plans, reviewing finances and
  chargebacks, import/export, settings, inviting the team.
- **Data migration:** import from legacy systems and CSV.
- **Access:** QR scanner at the entrance (Raspberry Pi, sub-project
  `gymportal-qr-scanner`), rolling QR codes, NFC tags.
- **Several gyms:** one user can belong to several organizations and switch
  between them.

## Capabilities and Constraints

**Capabilities (in place):** member management with filters and saved filters;
memberships and plans including add-ons, discount phases, pauses and
cancellations; payments via Mollie including chargebacks and execution days;
credit and top-ups; access control (live log, devices, statistics);
aggregators (e.g. Wellpass); blocklist; documents; real-time notifications;
import/export; online widget; member PWA; guest portal; team and role
management; user impersonation for platform admins; per-user light/dark/automatic
appearance.

**Technical constraints:**
- Tenant isolation via `gym_id` is mandatory for every query, endpoint and
  export; no access across gym boundaries.
- Production runs on PostgreSQL (MariaDB locally); migrations are additive and
  backward compatible because they roll out during live operation.
- Laravel 12, Vue 3 + Inertia 2, Tailwind CSS 4; status: open beta.
- Member PWA, branded app, guest portal, landing page, QR scanner and OCR
  import live in separate repositories (see CLAUDE.md).

**Terminology (German UI):** „Mitglied“ (member), „Vertrag“ (membership based
on a plan), „Organisation“ or „Studio“ (tenant), „Check-in“, „Gästeportal“
(guest portal), „Simulation“ (impersonation).

## Brand Commitments

- **Name:** gymportal.io.
- **Logo:** SVG wordmark with a violet `#8b5cf6` → cyan `#06b6d4` gradient; the
  backend (`resources/js/Components/Logo.vue`) and the landing page use the
  same path data.
- **Typeface:** Archivo, self-hosted, shared with the landing page (decided
  November 2026).
- **Form of address:** gym operators are addressed formally („Sie“) in the
  backend and in emails to operators; members (widget, PWA, branded app, emails
  to members) informally („Du“). Interface copy is German.
- **Privacy:** fonts and assets are self-hosted; no third-party requests unless
  necessary (GDPR).

## Evidence on Hand

- Public pricing and trial terms: see Positioning.
- License: `LICENSE.md` (GNU GPLv3).
- **Not available and not to be invented:** customer counts, testimonials, case
  studies, benchmarks or certifications.

## Product Principles

1. **Gym operations come first.** Front-desk tasks take seconds: search instead
   of navigate, dense information, no detours.
2. **Open and fair.** Open source and a fixed price apply to the interface too:
   no dark patterns, no hidden costs, no lock-in.
3. **No media breaks.** Online signup, payment and access connect; a workflow
   never ends in paper or a third-party system.
4. **Tenant isolation is non-negotiable.** Every new feature is first checked
   for data becoming visible between gyms.
5. **Accessible by default,** not as rework (see below).

## Accessibility & Inclusion

**WCAG 2.1 AA is binding** for the backend and every surface members and
guests see, in light and dark. For online contract signup (widget, member PWA)
the German Accessibility Strengthening Act (BFSG) has also applied since June
2025. Checks run with axe-core against WCAG 2.1 level A **and** AA, including
keyboard operation and touch targets.
