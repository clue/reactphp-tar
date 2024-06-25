<?php

namespace Clue\Tests\React\Tar;

use Clue\React\Tar\TarDecoder;
use React\Stream\ThroughStream;
use React\Stream\ReadableStreamInterface;

class TarDecoderTest extends TestCase
{
    private $decoder;

    /**
     * @before
     */
    public function setUpTarDecoder()
    {
        $this->decoder = new TarDecoder();
    }

    public function testWriteLessDataThanBufferSizeWillNotEmitAnyEvents()
    {
        $this->decoder->on('entry', $this->expectCallableNever());
        $this->decoder->on('close', $this->expectCallableNever());

        $ret = $this->decoder->write('data');

        $this->assertTrue($ret);
    }

    public function testWriteInvalidDataWillEmitErrorAndReturnFalse()
    {
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $ret = $this->decoder->write(str_repeat('2', 1024));

        $this->assertFalse($ret);
    }

    public function testWriteInvalidDataWithValidHeaderButInvalidChecksumWillEmitErrorAndReturnFalse()
    {
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $data = 'x' . file_get_contents(__DIR__ . '/fixtures/alice-bob.tar', false, null, 1, 512 - 1);
        $ret = $this->decoder->write($data);

        $this->assertFalse($ret);
    }

    public function testWriteToClosedDecoderDoesNothing()
    {
        $this->decoder->close();

        $ret = $this->decoder->write(str_repeat('x', 1024));

        $this->assertFalse($ret);
    }

    public function testWriteDataExactlyOneBlockWillDecodeHeaderAndStartStreamingEntry()
    {
        $never = $this->expectCallableNever();
        $this->decoder->on('entry', function (array $header, ReadableStreamInterface $stream) use ($never) {
            $stream->on('end', $never);
            $stream->on('close', $never);
        });
        $this->decoder->on('entry', $this->expectCallableOnce());

        $data = file_get_contents(__DIR__ . '/fixtures/alice-bob.tar', false, null, 0, 512);
        $ret = $this->decoder->write($data);

        $this->assertTrue($ret);
    }

    public function testWriteDataOneBlockPlusDataAndIncompletePaddingWillDecodeHeaderAndEndAndCloseEntryAndAwaitMorePadding()
    {
        $data = $this->expectCallableOnceWith("bob\n");
        $end = $this->expectCallableOnce();
        $close = $this->expectCallableOnce();
        $this->decoder->on('entry', function (array $header, ReadableStreamInterface $stream) use ($data, $end, $close) {
            $stream->on('data', $data);
            $stream->on('end', $end);
            $stream->on('close', $close);
        });
        $this->decoder->on('entry', $this->expectCallableOnce());

        $data = file_get_contents(__DIR__ . '/fixtures/alice-bob.tar', false, null, 0, 512 + 10);
        $ret = $this->decoder->write($data);

        $this->assertTrue($ret);

        $ref = new \ReflectionProperty($this->decoder, 'padding');
        $ref->setAccessible(true);

        $this->assertEquals(512 - 10, $ref->getValue($this->decoder));
    }

    public function testWriteDataExactlyOneBlockWithEmptyFileEntryWillDecodeHeaderAndImmediatelyEndAndCloseEntry()
    {
        $data = $this->expectCallableNever();
        $end = $this->expectCallableOnce();
        $close = $this->expectCallableOnce();
        $this->decoder->on('entry', function (array $header, ReadableStreamInterface $stream) use ($data, $end, $close) {
            $stream->on('data', $data);
            $stream->on('end', $end);
            $stream->on('close', $close);
        });
        $this->decoder->on('entry', $this->expectCallableOnce());

        $data = file_get_contents(__DIR__ . '/fixtures/single-empty.tar', false, null, 0, 512);
        $ret = $this->decoder->write($data);

        $this->assertTrue($ret);
    }

    public function testWriteDataWhenStreamingEntryWillEmitDataOnEntryWithoutEndWhenMoreDataIsRemaining()
    {
        $entry = new ThroughStream();
        $entry->on('data', $this->expectCallableOnceWith('hi'));
        $entry->on('end', $this->expectCallableNever());
        $entry->on('close', $this->expectCallableNever());

        $ref = new \ReflectionProperty($this->decoder, 'streaming');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, $entry);

        $ref = new \ReflectionProperty($this->decoder, 'remaining');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, 100);

        $ret = $this->decoder->write('hi');

        $this->assertTrue($ret);
    }

    public function testWriteDataWhenStreamingEntryIsClosedAlreadyWillNotEmitDataAndWillNotThrottleWhenMoreDataIsRemaining()
    {
        $entry = new ThroughStream();
        $entry->close();
        $entry->on('data', $this->expectCallableNever());

        $ref = new \ReflectionProperty($this->decoder, 'streaming');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, $entry);

        $ref = new \ReflectionProperty($this->decoder, 'remaining');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, 100);

        $ret = $this->decoder->write('hi');

        $this->assertTrue($ret);
    }

    public function testWriteDataWhenStreamingEntryIsPausedAlreadyWillEmitDataOnEntryAndThrottleWhenMoreDataIsRemaining()
    {
        $entry = new ThroughStream();
        $entry->pause();
        $entry->on('data', $this->expectCallableOnceWith('hi'));
        $entry->on('end', $this->expectCallableNever());
        $entry->on('close', $this->expectCallableNever());

        $ref = new \ReflectionProperty($this->decoder, 'streaming');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, $entry);

        $ref = new \ReflectionProperty($this->decoder, 'remaining');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, 100);

        $ret = $this->decoder->write('hi');

        $this->assertFalse($ret);
    }

    public function testWriteDataWhenStreamingEntryIsPausedDuringDataWillEmitDataOnEntryAndThrottleWhenMoreDataIsRemaining()
    {
        $entry = new ThroughStream();
        $entry->on('data', function () use ($entry) {
            $entry->pause();
        });
        $entry->on('end', $this->expectCallableNever());
        $entry->on('close', $this->expectCallableNever());

        $ref = new \ReflectionProperty($this->decoder, 'streaming');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, $entry);

        $ref = new \ReflectionProperty($this->decoder, 'remaining');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, 100);

        $ret = $this->decoder->write('hi');

        $this->assertFalse($ret);
    }

    public function testWriteDataWhenStreamingEntryIsPausedDuringDataAndResumeEntryAfterwardsWillEmitDrainEventOnDecoder()
    {
        $ref = null;
        $this->decoder->on('entry', function (array $header, ReadableStreamInterface $stream) use (&$ref) {
            $ref = $stream;
            $stream->pause();
        });
        $this->decoder->on('entry', $this->expectCallableOnce());

        $data = file_get_contents(__DIR__ . '/fixtures/alice-bob.tar', false, null, 0, 512 + 1);
        $ret = $this->decoder->write($data);

        $this->assertFalse($ret);

        $this->decoder->on('drain', $this->expectCallableOnce());

        $this->assertNotNull($ref);
        $ref->resume();
    }

    public function testWriteDataWhenStreamingEntryIsPausedDuringDataAndCloseEntryAfterwardsWillEmitDrainEventOnDecoder()
    {
        $ref = null;
        $this->decoder->on('entry', function (array $header, ReadableStreamInterface $stream) use (&$ref) {
            $ref = $stream;
            $stream->pause();
        });
        $this->decoder->on('entry', $this->expectCallableOnce());

        $data = file_get_contents(__DIR__ . '/fixtures/alice-bob.tar', false, null, 0, 512 + 1);
        $ret = $this->decoder->write($data);

        $this->assertFalse($ret);

        $this->decoder->on('drain', $this->expectCallableOnce());

        $this->assertNotNull($ref);
        $ref->close();
    }

    public function testWriteDataWhenStreamingEntryWillEmitDataOnEntryAndEndAndCloseWhenRemainingDataIsMatched()
    {
        $entry = new ThroughStream();
        $entry->on('data', $this->expectCallableOnceWith('hi'));
        $entry->on('end', $this->expectCallableOnce());
        $entry->on('close', $this->expectCallableOnce());

        $ref = new \ReflectionProperty($this->decoder, 'streaming');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, $entry);

        $ref = new \ReflectionProperty($this->decoder, 'remaining');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, 2);

        $ret = $this->decoder->write('hi');

        $this->assertTrue($ret);
    }

    public function testWriteDataWhenExpectingPaddingWillDiscardPaddingDataWhenMorePaddingIsExpected()
    {
        $ref = new \ReflectionProperty($this->decoder, 'padding');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, 4);

        $ret = $this->decoder->write("\x00\x00");

        $this->assertTrue($ret);

        $this->assertEquals(2, $ref->getValue($this->decoder));
    }

    public function testWriteDataWhenExpectingPaddingWillDiscardPaddingDataAndSaveToBufferWhenPaddingIsMatched()
    {
        $ref = new \ReflectionProperty($this->decoder, 'padding');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, 1);

        $ret = $this->decoder->write("\x00\x00");

        $this->assertTrue($ret);

        $this->assertEquals(0, $ref->getValue($this->decoder));

        $ref = new \ReflectionProperty($this->decoder, 'buffer');
        $ref->setAccessible(true);

        $this->assertEquals("\x00", $ref->getValue($this->decoder));
    }

    public function testClosingWillMakeItNoLongerWritable()
    {
        $this->assertTrue($this->decoder->isWritable());

        $this->decoder->close();

        $this->assertFalse($this->decoder->isWritable());
    }

    public function testClosingWillEmitCloseEvent()
    {
        $this->decoder->on('close', $this->expectCallableOnce());
        $this->decoder->on('error', $this->expectCallableNever());

        $this->decoder->close();

        $this->assertEquals(array(), $this->decoder->listeners('close'));
    }

    public function testClosingTwiceWillEmitCloseEventOnce()
    {
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->decoder->close();
        $this->decoder->close();
    }

    public function testCloseWithPendingDataWillCloseWithoutEmittingError()
    {
        $this->decoder->on('error', $this->expectCallableNever());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->decoder->write('data');
        $this->decoder->close();
    }

    public function testCloseWhenStreamingEntryWillEmitCloseOnEntryAndOnDecoder()
    {
        $this->decoder->on('error', $this->expectCallableNever());
        $this->decoder->on('close', $this->expectCallableOnce());

        $entry = new ThroughStream();
        $entry->on('data', $this->expectCallableNever());
        $entry->on('end', $this->expectCallableNever());
        $entry->on('error', $this->expectCallableNever());
        $entry->on('close', $this->expectCallableOnce());

        $ref = new \ReflectionProperty($this->decoder, 'streaming');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, $entry);

        $this->decoder->close();
    }

    public function testEndWithPendingDataWillEmitErrorAndClose()
    {
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->decoder->write('data');
        $this->decoder->end();
    }

    public function testEndWhenStreamingEntryWillEmitErrorAndCloseOnEntryAndOnDecoder()
    {
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $entry = new ThroughStream();
        $entry->on('data', $this->expectCallableNever());
        $entry->on('end', $this->expectCallableNever());
        $entry->on('error', $this->expectCallableOnce());
        $entry->on('close', $this->expectCallableOnce());

        $ref = new \ReflectionProperty($this->decoder, 'streaming');
        $ref->setAccessible(true);
        $ref->setValue($this->decoder, $entry);

        $this->decoder->end();
    }
}
