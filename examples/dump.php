<?php

require __DIR__ . '/../vendor/autoload.php';

$in = isset($argv[1]) ? $argv[1] : (__DIR__ . '/../tests/fixtures/alice-bob.tar');
echo 'Reading file "' . $in . '" (pass as argument to example)' . PHP_EOL;

$stream = new React\Stream\ReadableResourceStream(fopen($in, 'r'));

$decoder = new Clue\React\Tar\TarDecoder();
$decoder->on('entry', function (array $header, React\Stream\ReadableStreamInterface $file) {
    static $i = 0;
    echo 'FILE #' . ++$i . PHP_EOL;

    echo 'Received entry headers:' . PHP_EOL;
    var_dump($header);

    $contents = '';
    $file->on('data', function ($chunk) use (&$contents) {
        $contents .= $chunk;
    });
    $file->on('close', function () use (&$contents) {
        echo 'Received entry contents (' . strlen($contents) . ' bytes)' . PHP_EOL;

        $d = new Clue\Hexdump\Hexdump();
        echo $d->dump($contents) . PHP_EOL . PHP_EOL;
    });
});
$decoder->on('error', function ($error) {
    echo 'ERROR: ' . $error . PHP_EOL;
});
$decoder->on('close', function() {
    echo 'CLOSED' . PHP_EOL;
});

$stream->pipe($decoder);
