# UPGRADING TO VERSION 3

Composer Test Scenarios versions 1.x and 2.x were implemented as simple scripts that ran from post-update command handlers. Version 3 has been re-implemented as a Composer Installer; in this version, scenarios are described in the `extras` section of the project's composer.json file.

## Converting post-update-cmd Definitions

The post-update-cmd block below creates a `symfony4` scenario in Composer Test Scenarios version 2:
```
    "post-update-cmd": [
        "create-scenario symfony4 'symfony/console:^4.0' --platform-php '7.1.3'"
    ],
```
In version 3, this would instead be done as follows:
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
            }
        },
    },

```
Composer Test Scenarios now runs as needed after `composer update` commands; it is no longer necessary to configure it to do this in a `post-update-cmd`. As you can see from the above example, to change a portion of the composer.json file, all that is required is to add the desired settings to the scenario definition in the `scenarios` section of the composer.json's `extra` block.

## Converting create-scenario Script Options

* `--platform-php` / `--unset-platform-php`: Define a new `platform.php`, follow the pattern shown in the `symfony4` example above. To un-set the `platform.php` definition, define an empty `platform` section in the `config` section of the scenario data.

* `--stability`: Define a `stability` entry in the scenario data.

* `--create-lockfile` / `--no-lockfile`: This setting can be set as a scenario option.

* `--autoload-dir`: Not applicable. Version 3 of Composer Test Scenarios now correctly fixes up the `autoload` and `autoload-dev` sections of the scenario's composer.lock file, so it is no longer necessary to explicitly name your autoload directories.

* `--remove`: Packages that should be removed from a scenario may be listed in a `remove` section of the scenario data.

* `--keep`: Not currently implemented in 3.x.

## Remove Incompatible Scripts

If the `scripts` section of your composer.json file defines a "scenario" command, then delete it.

## Updating Test Script

Replace any occurance of:
```
composer scenario "${SCENARIO}" "${DEPENDENCIES}"
```
or
```
scenarios/install "${SCENARIO}" "${DEPENDENCIES}"
```
with:
```
.scenarios.lock/install "${SCENARIO}" "${DEPENDENCIES}"
```
