# moodle-psalm-plugin
A [Psalm](https://github.com/vimeo/psalm) plugin to detect unsafe usage of `$DB` methods with SQL

Installation:

```console
$ composer require --dev klebann/psalm-moodle-plugin
$ vendor/bin/psalm-plugin enable klebann/psalm-moodle-plugin
```

Usage:

Run your usual Psalm command:
```console
$ vendor/bin/psalm --config=psalm-plugin.xml --no-diff
```

Explanation:

...
