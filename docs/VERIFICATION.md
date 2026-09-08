# Verification notes

Verification baseline: 8 September 2026.

## Checks performed for this release

| Check | Result |
| --- | --- |
| TypeScript type checking | Passed |
| ESLint with no warnings | Passed |
| Frontend pricing tests | 4 tests passed |
| Production Vite build | Passed |
| PHP syntax | 191 source/configuration/test files checked; no errors |
| PHP decimal unit tests | 10 tests, 19 assertions passed |
| PHPStan, level 5 | Passed; no errors |
| Laravel Pint | Passed |
| Bash helper syntax | Passed |

The local PHP checks used PHP 8.3.6 with locked Composer dependencies. Frontend checks used locked npm dependencies. These checks do not substitute for clean dependency installation or a running PostgreSQL feature suite.

## Full-runtime validation

GitHub Actions completed successfully against an isolated PostgreSQL service on 8 September 2026. The workflow installs locked dependencies, builds the frontend, runs static analysis and formatting checks, executes the backend test suite, and performs dependency audits. The current status is available from the CI badge in the README.

Earlier documentation stated 103 tests and 471 assertions. Those historical counts are not reused as proof for this release. A scaffold-only unit test that asserted `true` was removed; meaningful business tests remain.

Browser-based checkout and mobile interaction remain manual release checks. The screenshot gallery and captioned product tour document the current user experience; see [media provenance](DEMO.md).

## Release hardening

- Fixed the login form's Remember me binding and added a show/hide password control.
- Added a keyboard skip link, chart series labels and a chart empty state; disabled chart animation so the whole series appears immediately.
- Corrected the quoted application name in the example environment.
- Replaced fixed Docker network assumptions with Compose service networking; published the local preview port and bound it to loopback.
- Added a first-install demo helper that stops if an environment file already exists.
- Updated the README and supporting setup, architecture and operating guides.

This verification record covers maintainability, static analysis, unit tests and build checks. It is not a penetration test, load test or production-readiness certification.
