# Composer Test Scenarios

Manage multiple "test scenarios", each with a different set of Composer dependencies.

## Use Case

Your composer.json file contains a requirement with multiple versions, such as:
```
    "require": {
        "symfony/console": "^2.8|^3|^4",
        ...
    }
```
If you wish to be able to easily test all of these options, then this project may be used to define different test scenarios to cover all of the relevant variants. When using this project, multiple composer.lock files are committed to the repository to allow [lowest, current, highest testing](https://blog.wyrihaximus.net/2015/06/test-lowest-current-and-highest-possible-on-travis/) to be done on any of the scenarios.

- **Current** tests run against the specific dependencies in a committed composer.lock file, ensuring that the dependencies being used do not change during the implementation of a new feature.

- **Highest** tests first run `composer update` to ensure that all dependencies are brought up to date before the tests are run. If the current tests are passing, but the highest tests fail, it is an indication that some dependency might have accidentally introduced a change that is not backwards compatible with previous versions.

- **Lowest** tests first run `composer update --prefer-lowest` to install the absolute lowest version permitted by the project's version constraints. If the lowest tests fail, but other tests pass, it indicates that the current feature may have introduced calls to some dependency APIs not available in the versions specified in the project's composer.json file.

This project makes it easy to do lowest, current, highest testing on different sets of the project's dependencies. For example, a project might wish to compare the current Symfony 3.4.x dependencies with the latest Symfony 3.4.x dependencies, and also compare the current Symfony 4 dependencies with the latest Symfony 4 dependencies.

## Usage

Use the [example composer.json file](example-composer.json) as a guide to set up your project's composer.json file. Alter the `create-scenario` commands in the `post-update-cmd` script to create the test scenarios you need.

Next, use the [example .travis.yml]() file as a starting point for your tests. Alter the test matrix as necessary to test highest, current and/or lowest dependencies as desired for each of the scenarios. Any scenario referenced in your `.travis.yml` file must be defined in your `composer.json` file.

- Call `create-scenario name` to create a test scenario with the specified name.
- Use additional arguments to list the Composer requirements to use in this scenario, e.g. `symfony/console:^2.8`
- Other flags are available to alter the scenario's composer.json file as needed:
  - `--platform-php 7.0`: set the platform php version (recommended). Default: unset.
  - `--stability stable`: set the stability. Default: stable
  - `--create-lockfile`: create a composer.lock file to commit. This is the default.
  - `--no-lockfile`: create a modified composer.json file, but omit the composer.lock file. You may specify this option for any scenario that has only **highest** or **lowest** tests (no **current**).

The matrix will define the php version to use in the test, the scenario to use, and whether to do a lowest, current or highest test.

- Define the `SCENARIO` environment variable to name the scenario to test. If this variable is not defined, then the composer.json / composer.lock at the root of the project will be used for the test run.
- Use the `HIGHEST_LOWEST` environment variable to specify whether a lowest, current or highest test should be done.
  - To do a **highest** test, set `HIGHEST_LOWEST=update`.
  - To do a **lowest** test, set `HIGHEST_LOWEST="update --prefer-lowest"`.
  - To do a **current** test, do leave `HIGHEST_LOWEST` undefined.

With this setup, all that you need to do to create your scenarios is to run `composer update`. Commit the entire contents of the generated `scenarios` directory. Thereafter, every subsequent time that you run `composer update`, your scenario lock files will also be brought up to date. Commit the changes to your scenarios directory whenever you commit your updated `composer.lock` file.

To do ad-hoc testing, run:
```
$ composer scenario symfony4
$ composer test
```
To go back to the default scenario, just run `composer install` (or `composer scenario`, which does the same thing).
