# Release process

Versioning follows [SemVer](https://semver.org/) (`vMAJOR.MINOR.PATCH`).
The single source of truth for the version is `version` in `package.json`;
Vite bakes it into the frontend at build time (shown in the sidebar).

**Principle:** the git tag marks the **merge commit on `main`**, i.e. exactly
the state that ships as the release. That is why the tag is set **only after
the merge**, not on `develop`.

## Steps

1. **Bump the version** on `develop` (commits only `package.json`, **no** tag,
   as configured in `.npmrc` → `git-tag-version=false`):

   ```bash
   npm version patch   # or: minor / major
   ```

   Result: commit `chore(release): vX.Y.Z` on `develop`.

2. **Create and merge the MR `develop` → `main`** (this creates the merge commit).

3. **Tag the merge commit** (locally on an up-to-date `main`):

   ```bash
   git checkout main && git pull
   git tag -a vX.Y.Z -m "chore(release): vX.Y.Z"
   git push origin vX.Y.Z      # GitLab
   git push github vX.Y.Z      # GitHub mirror
   ```

4. **Create the GitHub release** manually (changelog), e.g. from the compare view
   `…/compare/vX.Y.Z-1...vX.Y.Z`. The app links to `…/releases` from the
   sidebar.

## Why not let `npm version` create the tag?

`npm version` would put the tag on the `develop` commit, **before** the merge.
Merging into `main` creates a new merge commit, though, so the tag would not
point at the tip of `main`, which skews GitLab tags, releases and compare views.
Hence: always tag manually after the merge (step 3).
