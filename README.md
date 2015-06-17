# clue/tar-react [![Build Status](https://travis-ci.org/clue/php-tar-react.svg?branch=master)](https://travis-ci.org/clue/php-tar-react)

Async, streaming parser for the [TAR file format](https://en.wikipedia.org/wiki/Tar_%28computing%29) (Tape ARchive),
built on top of [React PHP](http://reactphp.org/).

Implements UStar (Uniform Standard Tape ARchive) format, introduced by the POSIX IEEE P1003.1

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

## License

MIT
