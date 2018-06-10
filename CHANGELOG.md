# Changelog

### 2.1.0 - 2018-06-10

* Allos projects to store scenarios in either the original location, "scenarios", or in the new recommended location, ".scenarios.lock". Projects must be converted manually.

### 2.0.0 - 2018-03-18

* BREAKING: Default to preserving value of platform php. Add --unset-platform-php option for clients that wish to remove this setting, as the former default behavior did.
* Add 'highest', 'lowest' and 'lock' aliases for dependency actions in install-scenario scripts, for more readable test script files.

### 1.0.3 - 2018-03-07

* Update dependency license information and copyright year in LICENSE file.

### 1.0.2 - 2018-01-17

* Add a `--remove` option to allow scenarios to remove dependencies.

### 1.0.1 - 2017-12-13

* Run 'composer validate' on the composer.json file.
* Fix quoting errors.

### 1.0.0 - 2017-12-01

* Initial release
