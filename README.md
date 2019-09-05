# clue/tar-react [![Build Status](https://travis-ci.org/clue/php-tar-react.svg?branch=master)](https://travis-ci.org/clue/php-tar-react)

Async, streaming parser for the [TAR file format](https://en.wikipedia.org/wiki/Tar_%28computing%29) (Tape ARchive),
built on top of [React PHP](http://reactphp.org/).

Implements UStar (Uniform Standard Tape ARchive) format, introduced by the POSIX IEEE P1003.1

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
$stream = new Stream(fopen('archive.tar', 'r'), $loop);

$decoder = new Decoder();

$decoder->on('entry', function ($header, ReadableStreamInterface $file) {
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

The recommended way to install this library is [through composer](https://getcomposer.org).
[New to composer?](https://getcomposer.org/doc/00-intro.md)

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
  want to use [clue/zlib-react](https://github.com/clue/php-zlib-react) on the compressed
  input stream before passing the decompressed stream to the tar decoder.
