<?php
namespace Icecave\Recoil\Kernel;

use Exception;

class KernelApi implements KernelApiInterface
{
    /**
     * Return a value to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling co-routine.
     */
    public function return_(StrandInterface $strand, $value = null)
    {
        $coroutine = $strand->current();

        $strand->returnValue($value);

        $strand->kernel()->execute($coroutine);
    }

    /**
     * Throw an exception to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param Exception       $exception The error to send to the calling co-routine.
     */
    public function throw_(StrandInterface $strand, Exception $exception)
    {
        $coroutine = $strand->current();

        $strand->throwException($exception);

        $strand->kernel()->execute($coroutine);
    }

    /**
     * Terminate execution of the strand.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->terminate();
    }

    /**
     * Suspend execution of the strand.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function suspend(StrandInterface $strand, callable $callback)
    {
        $strand->suspend();

        $callback($strand);
    }

    /**
     * Do nothing (delays execution of the strand until the next tick).
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function cooperate(StrandInterface $strand)
    {
    }
}