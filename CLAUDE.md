# Notes for Claude (and other agents)

## Running the test suite

The PHPUnit integration tests require Docker: a `testenv` image plus a
running MySQL `db` service on the `localarena_default` network (see
`Gruntfile.cjs`'s `shell:test` task and `compose.yaml`). Locally, the
suite is normally run with `grunt test` (which builds the `testenv`
image and runs `phpunit` against `tests/phpunit.xml`).

### Claude Code Remote (web) sessions

The guidance below applies **only** when running in a Claude Code Remote
execution environment (e.g. a Claude Code on the web session), where the
Docker daemon and image registry are **not** reliably available (image
pulls are blocked by the network policy), so the suite generally
**cannot be run in the sandbox**.

In that case, **do not** rely on running the tests in the sandbox.
Instead, push the branch, wait for a PR to be opened, and examine the
**CI** state for the test results. Investigate and address CI failures
from there.

If you are running locally (not in a remote/web session) with a working
Docker setup, just run the suite directly with `grunt test`.
