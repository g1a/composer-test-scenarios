# Changelog

### 3.0.4 - 2019-09-12

* Write the 'install' script even if there are no scenarios

### 3.0.3 - 2019-09-10

* Added support for drupal-composer/preserve-paths. (#10)
* Scenarios are incompatible with composer-patches plugin (#9)

### 3.0.2 - 2019-02-11

* Also fix up 'classmap' in autoload / autoload-dev ections.

### 3.0.1 - 2018-11-26

* Also fix up autoload files in scenario autoload sections

### 3.0.0 - 2018-11-21

* Converted to a Composer Installer
* `autoload` and `autoload-dev` paths now automatically handled.
* `installer-paths` are now automatically relocated.
* Copies of the `vendor` directory no longer created in scenario directories.
* Scenario definitions are now listed in `extra`.`scenarios` rather than via a `post-update-cmd` script.
* Dependency licenses now automatically updated on `composer update`.
* Scenarios directory is now always stored in ".scenarios.lock".

### 2.1.0 - 2018-06-10

* Allows projects to store scenarios in either the original location, "scenarios", or in the new recommended location, ".scenarios.lock". Projects must be converted manually.

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
