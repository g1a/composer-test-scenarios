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
If you wish to be able to easily test all of these options, then this project may be used to define different test scenarios to cover all of the relevant variants.

## Usage

Use the [example composer.json file](example-composer.json) as a guide to set up your project's composer.json file. Alter the `create-scenario` commands in the `post-update-cmd` script to create the test scenarios you need.

Next, use the [example .travis.yml]() file as a starting point for your tests. Alter the test matrix as necessary to test highest, current and/or lowest dependencies as desired for each of the scenarios.

Any scenario referenced in your `.travis.yml` file must be defined in your `composer.json` file.
