# Contributing to Green Canvas

Thanks for thinking about contributing. Green Canvas is a small project run primarily for one Green Party branch — pull requests that help other branches stand the app up are especially welcome.

## Before you start

- **Open an issue first** for anything that isn't obviously a bug fix. A 5-minute conversation up front saves the awkward "thanks but we're not going to merge this" later.
- For new-council deployment problems, please use the `data-format` issue label so they're easy to triage.
- Check the [data-formats doc](docs/data-formats.md) — many of the open issues are about adapting the importers to different councils' CSV shapes.

## Development

```bash
composer setup                          # install + migrate
php artisan canvassing:create-admin     # create your first user
composer dev                            # Laravel server, queue, log viewer, Vite HMR
composer test                           # PHPUnit
./vendor/bin/pint                       # Laravel Pint (code style)
```

[CLAUDE.md](CLAUDE.md) has a longer architecture overview if you're new to the codebase.

## Pull requests

- One logical change per PR. If you're tempted to lump unrelated fixes together, split them.
- Add or update tests for behavioural changes. The test suite is fast (under 5 seconds) and there's no excuse for landing untested logic.
- Run `./vendor/bin/pint` before pushing.
- Keep commit messages descriptive — the existing log uses a "what changed and why" style; please match it.
- Don't bundle unrelated formatting/refactoring. We'd rather have a small, reviewable diff.

## What we'd particularly love

- **Importer support for other councils' electoral-register / FOI formats.** The current shapes are documented in [docs/data-formats.md](docs/data-formats.md). A configurable column-mapping layer is on the roadmap; in the meantime, parameterised parser additions are very welcome.
- **Multi-constituency support.** The current model assumes one constituency per deployment. A `Constituency` model with `wards.constituency_id` is the planned shape — happy to discuss before you start.
- **Accessibility improvements.** Particularly around the map view and form inputs.
- **Documentation for non-Calderdale data sources.** If you successfully stand up the app for another council, a `docs/case-studies/<council>.md` walkthrough will help the next person.

## What we'd rather you don't open a PR for

- Cosmetic changes to working code (renaming variables, reformatting, restyling) without a behavioural reason. We're not going to merge those.
- Adding features for a use case you don't actually have. Speculative complexity is the main thing slowing this app down.
- Adding a new dependency without a clear, narrow reason. Each one is permanent maintenance.

## Licensing

By contributing, you agree your contribution is licensed under the AGPL-3.0, the same licence as the rest of the project. See [LICENSE](LICENSE).

## Code of Conduct

Be kind. The full text lives in [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## Security issues

Please don't open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md) for the private contact route.
