<!-- BEGIN:igne-standards v0.1.0 stacks=backend,laravel -->
# IGNE standards
<!-- Company rules for every coding agent (Claude Code, Codex, Cursor). Source: the IGNE AI standards. This block is replaced on update. Project content for this repo lives in .ai/guidelines/ and .ai/rules/, which Boost renders into its block below. -->

## How to work here
- Read the files next to anything you change and follow the pattern closest to it. When that pattern contradicts a rule in this file, follow the rule in new files and classes, leave existing code as it is, and say so in the PR.
- Change only what the task needs. Do not refactor files you are not already editing.
- Never bypass git hooks or suggest `--no-verify`.
- A change is done when the stack's checks below pass. No TODO or placeholder code unless the user asked for it. Remove code your change made unreachable, also in files you would not otherwise touch.
- Ask before: creating or editing pipeline, git-hook, container or deploy files (the file list is in `.claude/hooks/igne-devops-guard.sh`, which prompts in Claude Code); adding or upgrading a dependency (it needs a justification, a maintenance check and a licence check); migrations that alter or drop existing columns or tables; changes to how authentication works, to permissions or role mappings, or to an existing policy's rules (a new policy that follows the existing pattern needs no asking); anything touching production configuration or secrets. Otherwise proceed, and do not ask about what the codebase or this file already answers.

## Code
- We follow SOLID, DRY, KISS and YAGNI. In practice: no abstraction for fewer than two uses (the boundary classes the stack section requires excepted); no contract (interface or abstract class) without a second implementation or a test boundary; no repository layer unless it solves a named problem; one responsibility per class; composition over inheritance.
- Names say what a value holds: `invoiceLines`, `userInput`, `isActive`. Never `data`, `result`, `arr`, `usr` for a name you choose; a key fixed by a contract or a library (a response's `data`, React Query's `data`) is fine, so rename on destructure.
- One term per concept across code, API, database, logs and docs (a customer is a customer everywhere). Singular for one, plural for many, `productsById` for keyed collections.
- Comments explain why, not what; none when the why is obvious.
- Handle invalid states first and return or throw early, so the happy path is not nested.
- Typed boundaries: validate external input where it enters, map it into a typed object, and never pass loose arrays or bags through layers.
- Fail explicitly: a specific exception with the right status and message, never a silent fallback.
- No hardcoded user-facing text; use translation keys. Internal logs are English; errors shown to users are translated.
- Secrets never in code or committed config. Environment variables hold infrastructure and secrets only, read through the config layer, never in business code.
- Logs never contain personal data unless approved and masked.
- Access is denied by default; check permissions, not role names.

## Git
- Branches: `feature/{projectcode}-{ticket}-{short-description}` (`feature/OO-660-api-v2-client`: the Jira key carries the project code), `fix/{short-description}`, `hotfix/{short-description}`.
- One purpose per PR, about 400 changed lines; split larger work or add a short plan to the description.
- PR description headings: What changed; Why; How tested; Risks or breaking changes; Review this part in particular.

## Backend
- Datetimes are stored in UTC and `app.timezone` is UTC. Conversion to a user's timezone happens in presentation only, per user, never through a fixed zone in code or config.
- Migrations change structure only; the one exception is a data step a structural change cannot ship without. Other data changes are a one-off command that can be run per environment.
- New tables get UUID primary keys. Every foreign key is constrained with explicit cascade behaviour; lookup columns get an index.
- Side effects such as mail and external syncs run in queued, idempotent jobs.
- Authorization is enforced server-side on every request that reads or changes protected data; a restriction in the UI is never enough.
- Logs are structured (eventName, entityId, correlationId, severity).

## HTTP and APIs
- GET reads without side effects, POST creates, PUT replaces, PATCH partially updates, DELETE deletes.
- JSON keys are camelCase. Paths that point into a document (validation error pointers, `?sort`, `?filter`) use dot notation with zero-based indices: `lines.0.amount`.
- Errors follow RFC 9457 (`application/problem+json`) with a stable `code`; no private data in errors.
- Lists: `?pageNumber=1&pageSize=25`, `?filter=…`, `?sort=-createdAt`; filter and sort only on whitelisted fields; paginated responses return `data` and `meta`.
- Breaking API changes get a new version (`/v1`, `/v2`) and an announced removal date; renamed browser routes keep a redirect.

## Laravel
- If `composer.json` requires `laravel/framework` and `laravel/boost` is in neither `require` nor `require-dev`, stop and tell the user before doing anything else: `composer require laravel/boost --dev && php artisan boost:install`. Boost's guidelines and skills are assumed present; without them the rules here are incomplete.
- Boost's block below applies. Where this block and Boost's framework guidelines differ, this block wins; a project rule below the END marker that names a deviation wins over both for that project.
- Done means: `vendor/bin/pint --dirty` has been run, `vendor/bin/phpstan analyse` reports no errors and `php artisan test` passes (run the narrowest tests while iterating, the full suite once before you call it done).
- Every PHP file starts with `declare(strict_types=1);` and every signature is fully typed.
- No named arguments when calling framework methods (Laravel's backwards-compatibility promise excludes parameter names).
- Validation lives in a Form Request, even for one rule; authorization in a Policy or Gate; never a role check in code.
- Boundary flow: Form Request → DTO or Model → Action or Service → JsonResource or ResourceCollection. Controllers orchestrate only; an Action is one business operation, a Service groups related actions. Never return a model directly from an API.
- Controllers are invokable or use resource methods only: index, show, store, update, destroy, restore, forceDestroy, plus create and edit in Web controllers. `Web` and `Api` controllers live in separate namespaces.
- UUID keys: `HasUuids` on the model, `foreignUuid()->constrained()` in migrations (`foreignId()` while the referenced table still has an integer key).
- `$fillable` holds scalar columns only, never relation IDs; set relations with `associate`, `attach`, `sync`.
- Query through relationships, not foreign keys: `$organization->customerMessages()`, `whereBelongsTo($ticket)`, `$model->getKey()` rather than `->id`.
- No `protected $with`; eager load per query with `with()` or `loadMissing()`.
- Upsert with `firstOrNew`, `fill`, relation calls, then one `save`, not exists-then-create.
- A multi-column concept (address, money, period) is a value object with a custom cast; a fixed set is an enum with a cast; a DTO or value object replaces any array passed between layers.
- Cross-cutting reactions go through events and listeners; model-specific reactions through observers.
- Naming: tables plural snake_case; pivot tables singular, alphabetical (`subscriber_ticket`), pivot models `SubscriberTicket`; one morph name per relation (`commentable`) with `enforceMorphMap`; class suffixes Controller, Request, Resource, Action, Service, Policy, Rule, Scope, Cast, Observer, Listener, Notification, Job, Command, Exception; events in past tense (`OrderShipped`). Examples: the `igne-laravel-conventions` skill.
- Dependencies through the constructor, or through the action method's signature in controllers, jobs and listeners; a facade only where Laravel's own docs use one in that place.
- Blade templates hold presentation only.
- Factories: every model has one, with a state method for every role, status or variant the model can have; all fake data in tests, commands and seeders comes from a factory; seeders build development data from factories, and data that ships to production is a seeder run by a command.
- Tests: Pest, or the repo's existing PHPUnit suite; feature tests for user-visible behaviour, unit tests for isolated logic; cover the happy path, validation failures, authorization and the important edge cases.
- Required tooling: Laravel Boost, with `'agents' => ['claude_code' => ['guidelines_path' => 'AGENTS.md']]` in `config/boost.php` and `@php artisan boost:update --ansi` in Composer's `post-update-cmd`.
<!-- END:igne-standards -->

# This repository

`igne-agency/laravel-permissions` is a Composer package, not an application: there is no Artisan, no Boost and no database. Spaces, permission enums, roles and user-facing labels stay in the installing app; this package only builds permission names and lists from them.

- Checks: `composer lint` (Pint), `composer analyse` (PHPStan, level max, no baseline) and `composer test` (PHPUnit on Orchestra Testbench). Done means all three pass.
- Generated permission names are stored data in every installing app. Any change to an output string is a breaking change and needs a major version.
- Docs live in `docs/` and ship with the package. Each feature's doc is written in the PR that adds the feature and is linked from `README.md`.
