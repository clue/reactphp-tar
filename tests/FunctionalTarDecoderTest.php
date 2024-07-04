<?php

namespace Clue\Tests\React\Tar;

use Clue\React\Tar\TarDecoder;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;

class FunctionalTarDecoderTest extends TestCase
{
    private $decoder;

    /**
     * @before
     */
    public function setUpTarDecoderAndLoop()
    {
        $this->decoder = new TarDecoder();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testAliceBob()
    {
        $stream = $this->createStream('alice-bob.tar');

        $stream->pipe($this->decoder);

        Loop::run();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testAliceBobWithSmallBufferSize()
    {
        // a tiny buffer size will emit *lots* of individual chunks, but the parser should work as expected
        $stream = $this->createStream('alice-bob.tar', 11);

        $stream->pipe($this->decoder);

        Loop::run();
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

        Loop::run();
    }

    public function testCompleteEndSingleEmptyBehavesSameAsStreaming()
    {
        $this->decoder->on('entry', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());
        $this->decoder->on('error', $this->expectCallableNever());

        $this->decoder->end(file_get_contents(__DIR__ . '/fixtures/single-empty.tar'));
    }

    private function createStream($name, $readChunkSize = null)
    {
        return new ReadableResourceStream(fopen(__DIR__ . '/fixtures/' . $name, 'r'), null, $readChunkSize);
    }
}
