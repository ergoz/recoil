<?php
namespace Recoil\Stream;

use Exception;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;
use Recoil\Stream\Exception\StreamReadException;
use React\Stream\ReadableStreamInterface as ReadableReactStreamInterface;

/**
 * Exposes a React readable stream as a Recoil readable stream.
 */
class ReadableReactStream implements ReadableStreamInterface
{
    /**
     * @param ReadableReactStreamInterface $stream The underlying React stream.
     */
    public function __construct(ReadableReactStreamInterface $stream)
    {
        $this->stream = $stream;
        $this->buffer = '';

        $this->stream->pause();

        $this->stream->on('data',  [$this, 'onStreamData']);
        $this->stream->on('end',   [$this, 'onStreamEnd']);
        $this->stream->on('close', [$this, 'onStreamClose']);
        $this->stream->on('error', [$this, 'onStreamError']);
    }

    /**
     * [COROUTINE] Read data from the stream.
     *
     * Execution of the current strand is suspended until data is available or
     * the end of the data stream is reached.
     *
     * Read operations must be exclusive. If concurrent reads are attempted a
     * StreamLockedException is thrown.
     *
     * @param integer $length The maximum number of bytes to read.
     *
     * @return string                The data read from the stream.
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent reads are unsupported.
     * @throws StreamReadException   if an error occurs while reading from the stream.
     */
    public function read($length)
    {
        if ($this->strand) {
            throw new StreamLockedException;
        } elseif ($this->isClosed()) {
            throw new StreamClosedException;
        }

        if (!$this->buffer) {
            yield Recoil::suspend(
                function ($strand) {
                    $this->strand = $strand;
                    $this->stream->resume();
                }
            );

            $this->strand = null;
        }

        if (strlen($this->buffer) > $length) {
            $buffer = substr($this->buffer, 0, $length);
            $this->buffer = substr($this->buffer, $length);
        } else {
            $buffer = $this->buffer;
            $this->buffer = '';
        }

        yield Recoil::return_($buffer);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * [COROUTINE] Close this stream.
     *
     * Closing a stream indicates that no more data will be read from the
     * stream.
     */
    public function close()
    {
        if ($this->strand) {
            $this->strand->resumeWithException(new StreamClosedException);
            $this->strand = null;
        }

        $this->stream->close();
        $this->buffer = '';

        yield Recoil::noop();
    }

    /**
     * Check if this stream is closed.
     *
     * @return boolean True if the stream has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return !$this->buffer
            && !$this->stream->isReadable();
    }

    /**
     * @internal
     */
    public function onStreamData($data)
    {
        $this->buffer .= $data;

        if ($this->buffer) {
            $this->stream->pause();

            if ($this->strand) {
                $this->strand->resumeWithValue(null);
            }
        }
    }

    /**
     * @internal
     */
    public function onStreamEnd()
    {
        $this->stream->removeListener('data',  [$this, 'onStreamData']);
        $this->stream->removeListener('end',   [$this, 'onStreamEnd']);
        $this->stream->removeListener('error', [$this, 'onStreamError']);
        $this->stream->removeListener('close', [$this, 'onStreamClose']);

        if ($this->strand) {
            $this->strand->resumeWithValue(null);
        }
    }

    /**
     * @internal
     */
    public function onStreamClose()
    {
        $this->stream->removeListener('data',  [$this, 'onStreamData']);
        $this->stream->removeListener('end',   [$this, 'onStreamEnd']);
        $this->stream->removeListener('error', [$this, 'onStreamError']);
        $this->stream->removeListener('close', [$this, 'onStreamClose']);

        if ($this->strand) {
            $this->strand->resumeWithException(new StreamClosedException);
            $this->strand = null;
        }
    }

    /**
     * @internal
     */
    public function onStreamError(Exception $exception)
    {
        $this->strand->resumeWithException(new StreamReadException($exception));
        $this->strand = null;
    }

    private $stream;
    private $strand;
    private $buffer;
}
