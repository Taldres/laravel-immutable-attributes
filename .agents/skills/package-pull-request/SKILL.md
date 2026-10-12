---
name: package-pull-request
description: "Use this skill when opening, titling, labelling or describing a pull request for this package: the title becomes one line of the release notes, the label decides its section, and the description tells the reviewer what changed."
license: MIT
metadata:
  author: Dennis Petersmann
---

# Package Pull Request

## Primary Goal

Open pull requests whose title reads as one clear line in the release notes, whose label puts it in the right section, and whose description lets a reviewer see what changes for users.

## Workflow

1. Write the title as one sentence that a user of the package understands without opening the pull request. Start with an imperative verb (Add, Fix, Guard, Reject, Document) and name the effect, not the implementation.
2. Keep the title at 72 characters or fewer, and aim for 60. GitHub cuts a longer title with "…" and moves the rest into the first line of the description. Count before opening: `printf '%s' "the title" | wc -m`.
3. Leave out a type prefix such as `fix:` or `docs:`, the pull request number and the trailing period. Commit messages keep Conventional Commits; the pull request title does not.
4. Set one label for the category from `.github/release.yml`: `breaking`, `enhancement`, `bug`, `documentation`, `dependencies` or `maintenance`. Use `breaking` when something that worked before now throws or behaves differently, except when the change only closes a path the guard was meant to cover: that is a `bug`, released as a minor with the change described under "Behavior changes". A pull request without a label lands in "Other Changes". Add `skip-changelog` as well when it should stay out of the release notes.
5. Describe what changed for users, what stays as it is, and what was tested, including what was not run. Put anything a user must know before upgrading under a "Behavior changes" heading; the maintainer copies it into the release notes.
6. Leave `CHANGELOG.md` alone. Nobody opening a pull request can know which version it will land in, and parallel pull requests would conflict there. The release writes the changelog from the pull request titles.
7. Open the pull request against the branch the change belongs to: `main` for the upcoming major version, the maintenance branch such as `1.x` for a fix to an older one. Port a fix that applies to both in its own pull request.
8. Do not add AI attribution to the title, the description or the commits, and do not push, merge or publish without approval.

## References

- `.github/release.yml` (labels to release note sections, from the Laravel package skeleton)
- `.github/workflows/update-changelog.yml`
- `.github/CONTRIBUTING.md`
- `CHANGELOG.md`

## Examples

- `Guard the columns an increment writes and match keys as columns` (63 characters, `bug`)
- `Leave columns out of #[ImmutableModel] with except` (50 characters, `enhancement`)
- `Run the test suite on MySQL, MariaDB and PostgreSQL` (51 characters, `maintenance`)

## Anti-Patterns

- A title that GitHub cuts at "…", so that the sentence starts again in the description.
- A title that names a file or class instead of the effect, such as `Update GuardsImmutableAttributes.php`.
- A vague title such as `fix: guard increments` or `Improvements`.
- A long sentence that explains the whole change; the description does that.
- An edit of `CHANGELOG.md` in the pull request; the release workflow writes it.
- More than one category label, or none, so the line lands in the wrong section or in "Other Changes".
