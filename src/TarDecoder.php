<?php

namespace Clue\React\Tar;

use Evenement\EventEmitter;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;
use RuntimeException;

/**
 * Decodes a TAR stream and emits "entry" events for each individual file in the archive.
 *
 * At the moment, this class implements the the `UStar` (Uniform Standard Tape ARchive) format,
 * introduced by POSIX IEEE P1003.1. In the future, it should support more of
 * the less common alternative formats.
 *
 * @event entry(array $header, \React\Stream\ReadableStreamInterface $stream)
 * @event error(Exception $e)
 * @event close()
 */
class TarDecoder extends EventEmitter implements WritableStreamInterface
{
    private $buffer = '';
    private $writable = true;
    private $closing = false;
    private $paused = false;
    private $streaming = null;
    private $remaining = 0;
    private $padding = 0;
    private $format;

    const BLOCK_SIZE = 512;

    public function __construct()
    {
        $this->format = "Z100name/Z8mode/Z8uid/Z8gid/Z12size/Z12mtime/Z8checksum/Z1type/Z100symlink/Z6magic/Z2version/Z32owner/Z32group/Z8deviceMajor/Z8deviceMinor/Z155prefix/Z12unpacked";

        if (PHP_VERSION < 5.5) {
            // PHP 5.5 replaced 'a' with 'Z' (read X bytes and removing trailing NULL bytes)
            $this->format = str_replace('Z', 'a', $this->format); // @codeCoverageIgnore
        }
    }

    public function write($data)
    {
        if (!$this->writable) {
            return false;
        }

        // incomplete entry => read until end of entry before expecting next header
        if ($this->streaming !== null) {
            $data = $this->consumeEntry($data);

            // entry still incomplete => wait for next chunk
            if ($this->streaming !== null) {
                return !$this->paused;
            }
        }

        // trailing padding remaining => skip padding before expecting next header
        if ($this->padding !== 0) {
            $data = $this->consumePadding($data);

            // padding still remaining => wait for next chunk
            if ($this->padding !== 0) {
                return true;
            }
        }

        $this->buffer .= $data;

        while (isset($this->buffer[self::BLOCK_SIZE - 1])) {
            $header = substr($this->buffer, 0, self::BLOCK_SIZE);
            $this->buffer = (string)substr($this->buffer, self::BLOCK_SIZE);

            if (rtrim($header, "\0") === '') {
                // skip if whole header consists of null bytes
                // trailing nulls indicate end of archive, but continue reading next block anyway
                continue;
            }
            try {
                $header = $this->readHeader($header);
            } catch (RuntimeException $e) {
                // clean up before throwing
                $this->buffer = '';
                $this->writable = false;

                $this->emit('error', array($e));
                $this->close();
                return false;
            }

            $this->streaming = new ThroughStream();
            $this->remaining = $header['size'];
            $this->padding   = $header['padding'];

            // entry stream is not paused by default - unless explicitly paused
            // emit "drain" even when entry stream is ready again to support backpressure
            $that = $this;
            $paused =& $this->paused;
            $paused = false;
            $this->streaming->on('drain', function () use (&$paused, $that) {
                $paused = false;
                $that->emit('drain');
            });
            $this->streaming->on('close', function () use (&$paused, $that) {
                if ($paused) {
                    $paused = false;
                    $that->emit('drain');
                }
            });

            $this->emit('entry', array($header, $this->streaming));

            if ($this->remaining === 0) {
                $this->streaming->end();
                $this->streaming = null;
            } else {
                $this->buffer = $this->consumeEntry($this->buffer);
            }

            // incomplete entry => do not read next header
            if ($this->streaming !== null) {
                return !$this->paused;
            }

            if ($this->padding !== 0) {
                $this->buffer = $this->consumePadding($this->buffer);
            }

            // incomplete padding => do not read next header
            if ($this->padding !== 0) {
                return true;
            }
        }

        return true;
    }

    public function end($data = null)
    {
        if ($data !== null) {
            $this->write($data);
        }

        if ($this->streaming !== null) {
            // input stream ended but we were still streaming an entry => emit error about incomplete entry
            $this->streaming->emit('error', array(new \RuntimeException('TAR input stream ended unexpectedly')));
            $this->streaming->close();
            $this->streaming = null;

            // add some dummy data to also trigger error on decoder stream
            $this->buffer = '.';
        }

        if ($this->buffer !== '') {
            // incomplete entry in buffer
            $this->emit('error', array(new \RuntimeException('Stream ended with incomplete entry')));
            $this->buffer = '';
        }

        $this->writable = false;
        $this->close();
    }

    public function close()
    {
        if ($this->closing) {
            return;
        }

        $this->closing = true;
        $this->writable = false;
        $this->buffer = '';

        if ($this->streaming !== null) {
            // input stream ended but we were still streaming an entry => forcefully close without error
            $this->streaming->close();
            $this->streaming = null;
        }

        // ignore whether we're still expecting NUL-padding

        $this->emit('close');
        $this->removeAllListeners();
    }

    public function isWritable()
    {
        return $this->writable;
    }

    private function consumeEntry($buffer)
    {
        // try to read up to [remaining] bytes from buffer
        $data = substr($buffer, 0, $this->remaining);
        $len = strlen($data);

        // reduce remaining buffer by number of bytes actually read
        $buffer = substr($buffer, $len);
        $this->remaining -= $len;

        // emit chunk of data
        $ret = $this->streaming->write($data);

        // nothing remaining => entry stream finished
        if ($this->remaining === 0) {
            $this->streaming->end();
            $this->streaming = null;
        }

        // throttle input when streaming entry is still writable but returns false (backpressure)
        if ($ret === false && $this->streaming !== null && $this->streaming->isWritable()) {
            $this->paused = true;
        }

        return $buffer;
    }

    private function consumePadding($buffer)
    {
        if (strlen($buffer) > $this->padding) {
            // data exceeds padding => skip padding and continue
            $buffer = (string)substr($buffer, $this->padding);
            $this->padding = 0;

            return $buffer;
        }

        // less data than padding, skip only a bit of the padding and wait for next chunk
        $this->padding -= strlen($buffer);
        return '';
    }

    // https://github.com/mishak87/archive-tar/blob/master/Reader.php#L155
    private function readHeader($header)
    {
        $record = unpack($this->format, $header);

        // we only support "ustar" format (for now?)
        if ($record['magic'] !== 'ustar') {
            throw new RuntimeException('Unsupported archive type, expected "ustar", but found "' . $record['magic'] . '"');
        }

        // convert to decimal values
        foreach (array('uid', 'gid', 'size', 'mtime', 'checksum') as $key) {
            $record[$key] = octdec($record[$key]);
        }

        // calculate and compare header checksum
        $checksum = 0;
        for ($i = 0; $i < self::BLOCK_SIZE; $i++) {
            $checksum += 148 <= $i && $i < 156 ? 32 : ord($header[$i]);
        }
        if ($record['checksum'] != $checksum) {
            throw new RuntimeException('Invalid header checksum, expected "' . $record['checksum'] . '", but calculated "' . $checksum . '" (looks like the archive is corrupted)');
        }

        // padding consists of X NULL bytes after record entry until next BLOCK_SIZE boundary
        $record['padding'] = (self::BLOCK_SIZE - ($record['size'] % self::BLOCK_SIZE)) % self::BLOCK_SIZE;

        // filename consists of prefix and name
        $record['filename'] = $record['prefix'] . $record['name'];

        return $record;
    }
}
