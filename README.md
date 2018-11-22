# Composer Test Scenarios

Manage multiple "test scenarios", each with a different set of Composer dependencies.

## Overview

Composer Test Scenarios allows a project to define multiple sets of dependencies and Composer configuration directives, all in the same composer.json file.

Scenarios are defined in the `scenarios` section of the `extras` block of the composer.json file. For example, consider a project that runs its test suite against multiple versions of Symfony. The scenarios section shown below defines scenarios names "symfony4", "symfony2" and "phpunit4":
```
    "extra": {
        "scenarios": {
            "symfony4": {
                "require": {
                    "symfony/console": "^4.0"
                },
                "config": {
                    "platform": {
                        "php": "7.1.3"
                    }
                }
            },
            "symfony2": {
                "require": {
                    "symfony/console": "^2.8"
                },
                "config": {
                    "platform": {
                        "php": "5.4.8"
                    }
                },
                "scenario-options": {
                    "create-lockfile": "false"
                }
            },
            "phpunit4": {
                "require-dev": {
                    "phpunit/phpunit": "^4.8.36"
                },
                "config": {
                    "platform": {
                        "php": "5.4.8"
                    }
                }
            }
        },
        ...
    }
```
Whenever `composer update` runs, Composer Test Scenarios will use the scenario information provided to compose multiple composer.json files, one for each scenario. By default, each scenario also is given a composer.lock file. This setup allows tests to run with different requirement sets while still locking the specific versions used to a stable, reproducable set.

## Highest / Current / Lowest Testing

If you wish to be able to easily test all of the different significant possible dependency resolutions for your project, the usual course of action is to use [lowest, current, highest testing](https://blog.wyrihaximus.net/2015/06/test-lowest-current-and-highest-possible-on-travis/). The basic purpose of each of these tests are as follows:

- **Current** tests run against the specific dependencies in a committed composer.lock file, ensuring that the dependencies being used do not change during the implementation of a new feature. This provides assurance that any test failure is due to changes in the project code, and not because a dependency that happened to be updates since the last test run caused an issue.

- **Highest** tests first run `composer update` to ensure that all dependencies are brought up to date before the tests are run. If the 'current' tests are passing, but the 'highest' tests fail, it is an indication that some dependency might have accidentally introduced a change that is not backwards compatible with previous versions.

- **Lowest** tests first run `composer update --prefer-lowest` to install the absolute lowest version permitted by the project's version constraints. If the lowest tests fail, but other tests pass, it indicates that the current feature may have introduced calls to some dependency APIs not available in the versions specified in the project's composer.json file.

If your composer.lock holds Symfony 3 components, then the `highest` test can be used to test Symfony 4, and the `lowest` test can be used for Symfony 2. In practice, though, it is difficult to craft a composer.json file that can easily be altered to run all three scenarios. Multipe alterations are needed, and the test scripts become more complicated. Furthermore, if you wish to do current / highest testing on both Symfony 3.4 and Symfony 4, then there is nothing for it but to commit multiple composer.lock files.

Composer Test Scenarios solves this problem by making it easy to do this.

#### Install scenarios

Use the [example .travis.yml](example.travis.yml) file as a starting point for your tests. Alter the test matrix as necessary to test highest, current and/or lowest dependencies as desired for each of the scenarios. Any scenario referenced in your `.travis.yml` file must be defined in your `composer.json` file. The Travis test matrix will define the php version to use in the test, the scenario to use, and whether to do a lowest, current or highest test.

- Define the `SCENARIO` environment variable to name the scenario to test. If this variable is not defined, then the composer.json / composer.lock at the root of the project will be used for the test run.
- Use the `HIGHEST_LOWEST` environment variable to specify whether a lowest, current or highest test should be done.
  - To do a **highest** test, set `DEPENDENCIES=highest`.
  - To do a **lowest** test, set `DEPENDENCIES=lowest`.
  - To do a **current** test, set `DEPENCENCIES=lock`, or leave it undefined.
- Install the scenario to test using the `.scenarios.lock/install` script, as shown in [example .travis.yml](example.travis.yml).

With this setup, all that you need to do to create your scenarios is to run `composer update`. Commit the entire contents of the generated `scenarios` directory. Thereafter, every subsequent time that you run `composer update`, your scenario lock files will also be brought up to date. Commit the changes to your scenarios directory whenever you commit your updated `composer.lock` file.

#### Test locally

To do ad-hoc testing, run:
```
$ composer scenario symfony4
$ composer test
```
To go back to the default scenario, just run `composer install` (or `composer scenario default`, which does the same thing).

## Scenarios folder

Each scenario has its own `composer.lock` file (save for those scenarios created with the `"create-lockfile": "false"` option set). These lock files are stored in the `.scenarios.lock` directory, which is created automatically during `composer update` operations. The contents of the scenarios directory should be committed to your repository, just like the `composer.lock` file is.

## Dependency Licenses

Every time `composer update` is run, Composer Test Scenarios will run the `composer licenses` command and append the result to your project's LICENSE file (if any). The advantage of this is that prospective users will be able to confirm your project's licence compliance by browsing your LICENSE file.

As an added service, the Copyright notice in your LICENSE file is also updated to include the current year, if it is not already mentioned in the Copyright notice.
