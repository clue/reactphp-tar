<?php

use Clue\React\Tar\Decoder;
use React\EventLoop\StreamSelectLoop;
use React\Stream\Stream;

class FunctionDecoderTest extends TestCase
{
    private $decoder;

    public function setUp()
    {
        $this->decoder = new Decoder();
        $this->loop = new StreamSelectLoop();
    }

    public function testAliceBob()
    {
        $stream = $this->createStream('alice-bob.tar');

        $stream->pipe($this->decoder);

        $this->loop->run();
    }

    public function testAliceBobWithSmallBufferSize()
    {
        $stream = $this->createStream('alice-bob.tar');

        // a tiny buffer size will emit *lots* of individual chunks, but the parser should work as expected
        $stream->bufferSize = 11;

        $stream->pipe($this->decoder);

        $this->loop->run();
    }

    public function testStreamingSingleEmptyEmitsSingleEntryWithEmptyStream()
    {
        $stream = $this->createStream('single-empty.tar');

        $never = $this->expectCallableNever();
        $once = $this->expectCallableOnce();
        $that = $this;

        $this->decoder->on('entry', $this->expectCallableOnce());
        $this->decoder->on('entry', function ($entry, $stream) use ($that, $never, $once) {
            $that->assertEquals('empty', $entry['filename']);
            $that->assertEquals(0, $entry['size']);

            $that->assertTrue($stream->isReadable());

            $stream->on('error', $never);
            $stream->on('data', $never);
            $stream->on('close', $once);
        });

        $this->decoder->on('close', $this->expectCallableOnce());
        $this->decoder->on('error', $this->expectCallableNever());

        $stream->pipe($this->decoder);

        $this->loop->run();
    }

    public function testCompleteEndSingleEmtpyBehavesSameAsStreaming()
    {
        $this->decoder->on('entry', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());
        $this->decoder->on('error', $this->expectCallableNever());

        $this->decoder->end(file_get_contents(__DIR__ . '/fixtures/single-empty.tar'));
    }

    private function createStream($name)
    {
        return new Stream(fopen(__DIR__ . '/fixtures/' . $name, 'r'), $this->loop);
    }
}
