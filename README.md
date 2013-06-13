# Behat Parallel Runner

## Installation

This extension requires:

* Behat 2.4+

### Through Composer

1. Set dependencies in your **composer.json**:

```javascript
{
    "require": {
        ...
        "shvetsgroup/parallelrunner": "*"
    }
}
```

2. Install/update your vendors:

```bash
$ curl http://getcomposer.org/installer | php
$ php composer.phar install
```

## Configuration

As a bare minimum requirement, activate extension in your **behat.yml**:

```yml
default:
  extensions:
    shvetsgroup\ParallelRunner\Extension: ~
```

### Separate environments for test processes

In certain cases, it's useful to run testing processes in separate environments (e.g. if test results of one test could
break other test's data). For this purpose, you should define behat profiles in your configuration file and pass their
names to "profile" parameter of the extension like this:

```yml
default:
  extensions:
    shvetsgroup\ParallelRunner\Extension:
      profiles:
        - environment1
        - environment2
...

environment1:
  some_data: ...

environment2:
  some_data: ...
```

Now, let's say you've run the test with 4 parallel processes. The first process will be launched with "environment1"
profile, second with "environment2", and the rest with the default profile (or profile, which was passed in --config option).

### Running tests in parallel by default

If you want all of your tests to run in parallel, just specify default number of parallel processes in your configuration
file. Note: this number can be overridden by --parallel option.

```yml
default:
  extensions:
    shvetsgroup\ParallelRunner\Extension:
      process_count: 4
```

## Usage

Use "--parallel" or "-l" parameter to specify number of concurrent test processes. For example:

```bash
$ bin/behat -l 4
```

## Troubleshooting

1. If you're using Selenium, make sure it's launched in Hub mode to get all the benefits of parallelism (http://selenium-grid.seleniumhq.org/run_the_demo.html).

2. This extension does not work with features which have closures as definitions (you'll get "Serialization of 'Closure' is not allowed" errors most likely).
