<?php

namespace React\Promise;

use React\Promise\PromiseAdapter\CallbackPromiseAdapter;

class PromiseTest extends TestCase
{
    use PromiseTest\FullTestTrait;

    public function getPromiseTestAdapter(callable $canceller = null)
    {
        $resolveCallback = $rejectCallback = null;

        $promise = new Promise(function ($resolve, $reject) use (&$resolveCallback, &$rejectCallback) {
            $resolveCallback = $resolve;
            $rejectCallback  = $reject;
        }, $canceller);

        return new CallbackPromiseAdapter([
            'promise' => function () use ($promise) {
                return $promise;
            },
            'resolve' => $resolveCallback,
            'reject'  => $rejectCallback,
            'settle'  => $resolveCallback,
        ]);
    }

    /** @test */
    public function shouldRejectIfResolverThrowsException()
    {
        $exception = new \Exception('foo');

        $promise = new Promise(function () use ($exception) {
            throw $exception;
        });

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $promise
            ->then($this->expectCallableNever(), $mock);
    }

    /** @test */
    public function shouldRejectWithoutCreatingGarbageCyclesIfResolverThrowsException()
    {
        gc_collect_cycles();
        $promise = new Promise(function () {
            throw new \Exception('foo');
        });
        unset($promise);

        $this->assertSame(0, gc_collect_cycles());
    }
}
