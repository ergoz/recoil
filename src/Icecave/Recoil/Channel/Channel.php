<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Recoil;
use SplQueue;

/**
 * An unbuffered data channel.
 */
class Channel implements ReadableChannelInterface, WritableChannelInterface
{
    public function __construct()
    {
        $this->reads  = new SplQueue;
        $this->writes = new SplQueue;
        $this->closed = false;
    }

    /**
     * Read from this channel.
     *
     * @coroutine
     *
     * @return mixed                            The value read from the channel.
     * @throws Exception\ChannelClosedException if the channel has been closed.
     */
    public function read()
    {
        if ($this->isClosed()) {
            throw new Exception\ChannelClosedException($this);
        } elseif ($this->writes->isEmpty()) {
            $value = (yield Recoil::suspend(
                [$this->reads, 'push']
            ));
        } else {
            list($strand, $value) = $this->writes->dequeue();
            $strand->resume();
        }

        yield Recoil::return_($value);
    }

    /**
     * Write to this channel.
     *
     * @coroutine
     *
     * @param  mixed                            $value The value to write to the channel.
     * @throws Exception\ChannelClosedException if the channel has been closed.
     */
    public function write($value)
    {
        if ($this->isClosed()) {
            throw new Exception\ChannelClosedException($this);
        } elseif ($this->reads->isEmpty()) {
            yield Recoil::suspend(
                function ($strand) use ($value) {
                    $this->writes->push([$strand, $value]);
                }
            );
        } else {
            $strand = $this->reads->dequeue();
            $strand->resume($value);
        }
    }

    /**
     * Close this channel.
     *
     * @coroutine
     */
    public function close()
    {
        $this->closed = true;

        while (!$this->writes->isEmpty()) {
            list($strand) = $this->writes->pop();
            $strand->resumeWithException(
                new Exception\ChannelClosedException($this)
            );
        }

        while (!$this->reads->isEmpty()) {
            $strand = $this->reads->pop();
            $strand->resumeWithException(
                new Exception\ChannelClosedException($this)
            );
        }

        yield Recoil::noop();
    }

    /**
     * Check if a value can be read from the channel without blocking.
     *
     * @return boolean False if a call to read() will block; otherwise, true.
     */
    public function readyToRead()
    {
        return !$this->writes->isEmpty();
    }

    /**
     * Check if a value can be written to the channel without blocking.
     *
     * @return boolean False if a call to write() will block; otherwise, true.
     */
    public function readyForWrite()
    {
        return !$this->reads->isEmpty();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return $this->closed;
    }

    private $read;
    private $writes;
    private $closed;
}
