# Release-Prozess

Versionierung folgt [SemVer](https://semver.org/lang/de/) (`vMAJOR.MINOR.PATCH`).
Single Source of Truth für die Version ist die `version` in der `package.json`;
Vite brennt sie zur Build-Zeit ins Frontend (Anzeige in der Sidebar).

**Grundsatz:** Der git-Tag markiert den **Merge-Commit auf `main`** – also exakt
den Stand, der als Release ausgeliefert wird. Deshalb wird der Tag **erst nach
dem Merge** gesetzt, nicht auf `develop`.

## Ablauf

1. **Version bumpen** auf `develop` (committet nur `package.json`, **kein** Tag –
   so konfiguriert via `.npmrc` → `git-tag-version=false`):

   ```bash
   npm version patch   # oder: minor / major
   ```

   Ergebnis: Commit `chore(release): vX.Y.Z` auf `develop`.

2. **MR `develop` → `main`** erstellen und mergen (erzeugt den Merge-Commit).

3. **Tag auf den Merge-Commit setzen** (lokal auf aktuellem `main`):

   ```bash
   git checkout main && git pull
   git tag -a vX.Y.Z -m "chore(release): vX.Y.Z"
   git push origin vX.Y.Z      # GitLab
   git push github vX.Y.Z      # GitHub-Spiegel
   ```

4. **GitHub-Release** manuell anlegen (Changelog) – z. B. Compare-Ansicht
   `…/compare/vX.Y.Z-1...vX.Y.Z`. Die App verlinkt aus der Sidebar auf
   `…/releases`.

## Warum nicht `npm version` taggen lassen?

`npm version` würde den Tag auf den `develop`-Commit setzen – **vor** dem Merge.
Nach dem Merge nach `main` entsteht aber ein neuer Merge-Commit; der Tag zeigte
dann nicht auf den `main`-Tip, was GitLab-Tags/-Releases/-Compare verfälscht.
Darum: Tag immer manuell nach dem Merge (Schritt 3).
