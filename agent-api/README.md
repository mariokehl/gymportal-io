# Agent API für gymportal

Eine Schnittstelle, über die KI-Agenten und Automatisierungen für **ein Studio** auf gymportal
zugreifen: Fragen zu Mitgliedern, Verträgen, Zutritt, Zahlungen und Auslastung beantworten.

Aufsatz auf [mariokehl/gymportal-io](https://github.com/mariokehl/gymportal-io), Stand `develop`
v0.0.156. Lizenz wie das Projekt: GPL-3.0.

## Warum es das braucht

gymportal hat Schnittstellen für die Mitglieder-App (`/api/pwa`), das Anmelde-Widget
(`/api/widget`), den Gäste-Shop (`/api/guests`) und die Scanner (`/api/scanner`). Für den
Betreiber selbst gibt es nur die Oberfläche im Browser. Ein Agent, der dem Betreiber Arbeit
abnimmt, hat damit keinen Zugang. Diese API schließt die Lücke, ohne Fachlogik zu kopieren:
Kündigungstermine, Fristen und Pausenregeln kommen aus denselben Modellen und Diensten, die
Portal und Mitglieder-App benutzen.

## Zwei Zusagen

1. **Das Studio kommt aus dem Token.** Ein Token gehört zu genau einem Studio. Nichts im
   Aufruf kann ein anderes Studio wählen; ein Mitglied eines anderen Studios existiert für
   das Token nicht (404).
2. **Ein Token kann nur, was seine Rechte sagen.** Jeder Bereich hat ein eigenes Leserecht.
   Ein Agent, der nur Tarife kennen muss, sieht keine Mitglieder. Hat das Studio weder
   Testphase noch Abo, antwortet die API wie das Portal nicht mehr (403 `gym_without_access`).

Dazu ein Protokoll: jede Anfrage landet in `agent_api_logs` (wer, was, welches Mitglied,
Statuscode), ohne Inhalte der Antwort. Die Spalten `changed_data` und `changes` sind für
schreibende Aufrufe vorgesehen und bleiben beim Lesen leer.

## Endpunkte

Basis: `/api/v1/agent`, Anmeldung per `Authorization: Bearer <Token>`. Vollständig in
[`openapi.yaml`](openapi.yaml).

| Methode | Pfad | Recht | Wozu |
| --- | --- | --- | --- |
| GET | `/health` | – | Studio und Rechte des Tokens |
| GET | `/plans` | `plans.read` | Tarife, Preise, Laufzeit, Kündigungsfrist |
| GET | `/members` | `members.read` | Suche nach Name, E-Mail, Mitgliedsnummer |
| GET | `/members/lookup` | `members.read` | genau ein Mitglied per E-Mail, Nummer oder Telefon |
| GET | `/members/{id}` | `members.read` | Stammdaten |
| GET | `/members/{id}/contract` | `contracts.read` | Vertrag, nächster Kündigungstermin, Pause, Widerruf; im Gratis-Zeitraum zusätzlich der bezahlte Vertrag (`contract_membership`) |
| GET | `/members/{id}/access` | `access.read` | sollte der Zutritt gehen, und wenn nicht, warum |
| GET | `/members/{id}/check-ins` | `access.read` | letzte Besuche |
| GET | `/members/{id}/payments` | `payments.read` | Zahlungen, offene und fehlgeschlagene |
| GET | `/occupancy` | `stats.read` | Besuche je Wochentag und Stunde |
| GET | `/stats` | `stats.read` | Kennzahlen des Studios (jede Umsatzzahl nur mit `payments.read`) |
| GET | `/insights/inactive-members` | `stats.read` | laufender Vertrag, aber seit N Tagen kein Besuch |

Antworten haben immer dieselbe Hülle: `{"success": true, "data": {...}}` oder
`{"success": false, "error": {"code": "...", "message": "..."}}`.

## Token ausstellen

```bash
php artisan agent:token create --gym=<id oder slug> --name="Assistent" \
    --purpose="Beantwortet Mitgliederfragen" --abilities=read
php artisan agent:token list --gym=<id oder slug>
php artisan agent:token revoke --id=<id>
```

`--abilities` nimmt `read`, `all` oder eine Liste wie `members.read,contracts.read`.
Das Token wird einmal angezeigt und nur als Hash gespeichert. `--expires=2027-01-31` setzt ein
Ablaufdatum. Der Zweck ist Pflicht, weil er ins Verzeichnis der Verarbeitungstätigkeiten gehört.

## Beispiel

```bash
curl -H "Authorization: Bearer $TOKEN" "$URL/api/v1/agent/members/lookup?email=mia@example.org"
curl -H "Authorization: Bearer $TOKEN" "$URL/api/v1/agent/members/7/contract"
```

## Für KI-Agenten: MCP-Server

[`mcp/server.mjs`](mcp/server.mjs) stellt die API als 12 Werkzeuge über das Model Context
Protocol bereit (Node ab 18, keine Abhängigkeiten). Damit kann jeder MCP-fähige Agent
(Desktop-Assistenten, n8n, eigene Agenten) direkt damit arbeiten:

```json
{
  "mcpServers": {
    "gymportal": {
      "command": "node",
      "args": ["agent-api/mcp/server.mjs"],
      "env": { "GYMPORTAL_API_URL": "https://<portal>", "GYMPORTAL_API_TOKEN": "gpa_..." }
    }
  }
}
```

## Einbau in gymportal

Neue Dateien: `routes/agent.php`, `app/Http/Controllers/Api/Agent/*`,
`app/Http/Middleware/AuthenticateAgent.php`, `app/Models/GymApiClient.php`,
`app/Models/AgentApiLog.php`, `app/Console/Commands/AgentTokenCommand.php`, eine Migration
(zwei neue Tabellen, keine Änderung an bestehenden), `tests/Feature/Api/Agent/AgentApiTest.php`,
`database/seeders/AgentApiDemoSeeder.php`.

Geändert sind nur zwei bestehende Dateien, je um wenige Zeilen: `routes/api.php` lädt
`agent.php` im bestehenden `v1`-Block (Präfix `agent`), `bootstrap/app.php` kennt den Alias
`agent.auth` und nimmt `api/v1/agent/*` von der CSRF-Prüfung aus.

```bash
php artisan migrate
php artisan test tests/Feature/Api/Agent
php artisan db:seed --class=AgentApiDemoSeeder   # erfundene Daten zum Ausprobieren
```

## Tests

21 Tests in `tests/Feature/Api/Agent/AgentApiTest.php` gegen die echte Fachlogik (SQLite im
Speicher wie der Rest der Suite): Anmeldung, widerrufene und abgelaufene Tokens, Rechte,
Mandantentrennung, Vertragsdaten, Zutritt, Auslastung, Kennzahlen, Protokoll, Kommandozeile,
dazu ein Test, der `openapi.yaml` und den MCP-Server gegen die echten Routen hält.
