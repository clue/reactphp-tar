# clue/reactphp-tar

[![CI status](https://github.com/clue/reactphp-tar/actions/workflows/ci.yml/badge.svg)](https://github.com/clue/reactphp-tar/actions)
[![installs on Packagist](https://img.shields.io/packagist/dt/clue/tar-react?color=blue&label=installs%20on%20Packagist)](https://packagist.org/packages/clue/tar-react)

Streaming parser to extract tarballs with [ReactPHP](https://reactphp.org/).

The [TAR file format](https://en.wikipedia.org/wiki/Tar_%28computing%29) is a
common archive format to store several files in a single archive file (commonly
referred to as "tarball" with a `.tar` extension). This lightweight library
provides an efficient implementation to extract tarballs in a streaming fashion,
processing one chunk at a time in memory without having to rely on disk I/O.

**Table of Contents**

* [Quickstart example](#quickstart-example)
* [Install](#install)
* [Tests](#tests)
* [License](#license)
* [More](#more)

> Note: This project is in beta stage! Feel free to report any issues you encounter.

## Quickstart example

Once [installed](#install), you can use the following code to pipe a readable
tar stream into the `TarDecoder` which emits "entry" events for each individual file:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$stream = new React\Stream\ReadableResourceStream(fopen('archive.tar', 'r'));

$decoder = new Clue\React\Tar\TarDecoder();

$decoder->on('entry', function (array $header, React\Stream\ReadableStreamInterface $file) {
    echo 'File ' . $header['filename'];
    echo ' (' . $header['size'] . ' bytes):' . PHP_EOL;

    $file->on('data', function ($chunk) {
        echo $chunk;
    });
});

$stream->pipe($decoder);
```

See also the [examples](examples/).

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org/).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

While in beta, this project does not currently follow [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
composer require clue/tar-react:^0.2
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 8+.
It's *highly recommended to use the latest supported PHP version* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org/):

```bash
composer install
```

To run the test suite, go to the project root and run:

```bash
vendor/bin/phpunit
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.

## More

* If you want to learn more about processing streams of data, refer to the documentation of
  the underlying [react/stream](https://github.com/reactphp/stream) component.

* If you want to process compressed tarballs (`.tar.gz` and `.tgz` file extension), you may
  want to use [clue/reactphp-zlib](https://github.com/clue/reactphp-zlib) on the compressed
  input stream before passing the decompressed stream to the tar decoder.
