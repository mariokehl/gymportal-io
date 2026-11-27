# Agent API for gymportal.io

An interface through which AI agents and automations access **one gym** on gymportal.io:
answering questions about members, contracts, access, payments and occupancy.

Built on top of [mariokehl/gymportal-io](https://github.com/mariokehl/gymportal-io), based on
`develop` at v0.0.156. Licensed like the project: GPL-3.0.

## Why this is needed

gymportal.io has interfaces for the member app (`/api/pwa`), the sign-up widget
(`/api/widget`), the guest shop (`/api/guests`) and the scanners (`/api/scanner`). For the
operator themselves there is only the browser interface. An agent that takes work off the
operator's hands therefore has no way in. This API closes that gap without copying business
logic: cancellation dates, deadlines and pause rules come from the same models and services
that the portal and the member app use.

## Two guarantees

1. **The gym comes from the token.** A token belongs to exactly one gym. Nothing in the
   request can select another gym; a member of another gym does not exist for the token
   (404).
2. **A token can only do what its abilities allow.** Each area has its own read ability.
   An agent that only needs to know the plans sees no members. If the gym has neither a
   trial nor a subscription, the API stops answering, just like the portal (403
   `gym_without_access`).

On top of that there is an audit log: every request is written to `agent_api_logs` (who,
what, which member, status code), without the content of the response. The columns
`changed_data` and `changes` are reserved for write requests and stay empty for reads.

## Endpoints

Base: `/api/v1/agent`, authentication via `Authorization: Bearer <token>`. Fully described in
[`openapi.yaml`](openapi.yaml).

| Method | Path | Ability | Purpose |
| --- | --- | --- | --- |
| GET | `/health` | – | Gym and abilities of the token |
| GET | `/plans` | `plans.read` | Plans, prices, term, notice period |
| GET | `/members` | `members.read` | Search by name, email, member number |
| GET | `/members/lookup` | `members.read` | Exactly one member by email, number or phone |
| GET | `/members/{id}` | `members.read` | Master data |
| GET | `/members/{id}/contract` | `contracts.read` | Contract, next possible cancellation date, pause, withdrawal; during a free period also the paid contract (`contract_membership`) |
| GET | `/members/{id}/access` | `access.read` | Whether access should work by the scanner rules (`blockers`), and the last denied scan with the reason the portal stored (`last_denied_attempt`) |
| GET | `/members/{id}/access-attempts` | `access.read` | Scans and station check-ins, granted or denied (`result=denied`), with the portal's own denial reason, including other locations of the organisation |
| GET | `/members/{id}/check-ins` | `access.read` | Recent visits |
| GET | `/members/{id}/payments` | `payments.read` | Payments, open and failed |
| GET | `/occupancy` | `stats.read` | Visits by weekday and hour |
| GET | `/stats` | `stats.read` | Key figures of the gym (every revenue figure only with `payments.read`) |
| GET | `/insights/inactive-members` | `stats.read` | Running contract, but no visit for N days |

Responses always share the same envelope: `{"success": true, "data": {...}}` or
`{"success": false, "error": {"code": "...", "message": "..."}}`.

## Issuing tokens

```bash
php artisan agent:token create --gym=<id or slug> --name="Assistant" \
    --purpose="Answers member questions" --abilities=read
php artisan agent:token list --gym=<id or slug>
php artisan agent:token revoke --id=<id>
```

`--abilities` takes `read`, `all` or a list such as `members.read,contracts.read`.
The token is shown once and stored only as a hash. `--expires=2027-01-31` sets an expiry
date. The purpose is required because it belongs in the record of processing activities.

## Example

```bash
curl -H "Authorization: Bearer $TOKEN" "$URL/api/v1/agent/members/lookup?email=mia@example.org"
curl -H "Authorization: Bearer $TOKEN" "$URL/api/v1/agent/members/7/contract"
```

## For AI agents: MCP server

[`mcp/server.mjs`](mcp/server.mjs) exposes the API as 12 tools via the Model Context
Protocol (Node 18 or later, no dependencies). This lets any MCP-capable agent (desktop
assistants, n8n, custom agents) work with it directly:

```json
{
  "mcpServers": {
    "gymportal-io": {
      "command": "node",
      "args": ["agent-api/mcp/server.mjs"],
      "env": { "GYMPORTAL_API_URL": "https://<portal>", "GYMPORTAL_API_TOKEN": "gpa_..." }
    }
  }
}
```

## Integration into gymportal.io

New files: `routes/agent.php`, `app/Http/Controllers/Api/Agent/*`,
`app/Http/Middleware/AuthenticateAgent.php`, `app/Models/GymApiClient.php`,
`app/Models/AgentApiLog.php`, `app/Console/Commands/AgentTokenCommand.php`, one migration
(two new tables, no change to existing ones), `tests/Feature/Api/Agent/AgentApiTest.php`,
`database/seeders/AgentApiDemoSeeder.php`.

Only two existing files are changed, by a few lines each: `routes/api.php` loads
`agent.php` inside the existing `v1` group (prefix `agent`), and `bootstrap/app.php` registers
the `agent.auth` alias and excludes `api/v1/agent/*` from CSRF verification.

```bash
php artisan migrate
php artisan test tests/Feature/Api/Agent
php artisan db:seed --class=AgentApiDemoSeeder   # invented data to try things out
```

## Tests

21 tests in `tests/Feature/Api/Agent/AgentApiTest.php` against the real business logic
(in-memory SQLite like the rest of the suite): authentication, revoked and expired tokens,
abilities, tenant isolation, contract data, access, occupancy, key figures, audit log, command
line, plus a test that keeps `openapi.yaml` and the MCP server in sync with the actual routes.
