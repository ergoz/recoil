<?php
namespace Recoil\Kernel\Strand;

use Exception;
use LogicException;
use Recoil\Coroutine\CoroutineInterface;
use Recoil\Coroutine\CoroutineTrait;

/**
 * The base coroutine in a strand's call-stack.
 *
 * @internal
 */
class StackBase implements CoroutineInterface
{
    use CoroutineTrait;

    /**
     * Start the coroutine.
     *
     * @codeCoverageIgnore
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        throw new LogicException('Not supported.');
    }

    /**
     * Resume execution of a suspended coroutine by passing it a value.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     * @param mixed           $value  The value to send to the coroutine.
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        $strand->emit('success', [$strand, $value]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->pop();
        $strand->suspend();
    }

    /**
     * Resume execution of a suspended coroutine by passing it an exception.
     *
     * @param StrandInterface $strand    The strand that is executing the coroutine.
     * @param Exception       $exception The exception to send to the coroutine.
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        $throwException = true;

        $preventDefault = function () use (&$throwException) {
            $throwException = false;
        };

        $strand->emit('error', [$strand, $exception, $preventDefault]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->pop();
        $strand->suspend();

        if ($throwException) {
            throw $exception;
        }
    }

    /**
     * Inform the coroutine that the executing strand is being terminated.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->emit('terminate', [$strand]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->suspend();
    }
}
