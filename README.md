# clue/reactphp-tar [![Build Status](https://travis-ci.org/clue/reactphp-tar.svg?branch=master)](https://travis-ci.org/clue/reactphp-tar)

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
tar stream into the `Decoder` which emits "entry" events for each individual file:

```php
$loop = React\EventLoop\Factory::create();
$stream = new ReadableResourceStream(fopen('archive.tar', 'r'), $loop);

$decoder = new Decoder();

$decoder->on('entry', function (array $header, React\Stream\ReadableStreamInterface $file) {
    echo 'File ' . $header['filename'];
    echo ' (' . $header['size'] . ' bytes):' . PHP_EOL;

    $file->on('data', function ($chunk) {
        echo $chunk;
    });
});

$stream->pipe($decoder);

$loop->run();
```

See also the [examples](examples).

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/tar-react": "~0.1.0"
    }
}
```

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+.
It's *highly recommended to use PHP 7+* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

MIT

## More

* If you want to learn more about processing streams of data, refer to the documentation of
  the underlying [react/stream](https://github.com/reactphp/stream) component.

* If you want to process compressed tarballs (`.tar.gz` and `.tgz` file extension), you may
  want to use [clue/reactphp-zlib](https://github.com/clue/reactphp-zlib) on the compressed
  input stream before passing the decompressed stream to the tar decoder.
