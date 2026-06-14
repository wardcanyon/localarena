# Notes for Claude (and other agents)

## Running the test suite

The PHPUnit integration tests require Docker: a `testenv` image plus a
running MySQL `db` service on the `localarena_default` network (see
`Gruntfile.cjs`'s `shell:test` task and `compose.yaml`). In this remote
execution environment the Docker daemon and image registry are **not**
reliably available (image pulls are blocked by the network policy), so
the suite generally **cannot be run locally here**.

To verify changes, **do not** rely on running the tests in the sandbox.
Instead, push the branch, wait for a PR to be opened, and examine the
**CI** state for the test results. Investigate and address CI failures
from there.

For reference, the suite is normally run with `grunt test` (which builds
the `testenv` image and runs `phpunit` against `tests/phpunit.xml`).
