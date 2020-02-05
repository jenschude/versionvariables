This composer plugin makes it possible to use variable placeholders as a version string. Please be aware this is meant
as a proof of concept.

Usage:

```sh
composer require jenschude/version-variables`
```

In your composer json replace a version string with

```json
{
    "requires": {
       "vendor/package1": "0+:variablename",
       "vendor/package2": "0+:variablename"
    },
    "extra": {
        "versionvariables": {
            ":variablename": "^1.0"
        }
    }
}
```
