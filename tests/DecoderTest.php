<?php

use Clue\React\Tar\Decoder;

class DecoderTest extends TestCase
{
    private $decoder;

    public function setUp()
    {
        $this->decoder = new Decoder();
    }

    public function testWritingLessDataThanBufferSizeWillNotEmitAnyEvents()
    {
        $this->decoder->on('entry', $this->expectCallableNever());
        $this->decoder->on('close', $this->expectCallableNever());

        $this->decoder->write('data');
    }

    public function testWritingInvalidDataWillEmitError()
    {
        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->decoder->write(str_repeat('2', 1024));
    }

    public function testWritingToClosedDecoderDoesNothing()
    {
        $this->decoder->close();

        $this->decoder->write(str_repeat('x', 1024));
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
    }

    public function testClosingTwiceWillEmitCloseEventOnce()
    {
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->decoder->close();
        $this->decoder->close();
    }

    public function testClosingWithPendingDataWillEmitErrorAndCloseEvent()
    {
        $this->decoder->write('data');

        $this->decoder->on('error', $this->expectCallableOnce());
        $this->decoder->on('close', $this->expectCallableOnce());

        $this->decoder->close();
    }
}
