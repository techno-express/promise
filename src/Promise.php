<?php

namespace React\Promise;

use React\Promise\Internal\Queue;

final class Promise implements PromiseInterface
{
    private $canceller;
    private $result = null;
	
    private $state = 'pending';
    private static $loop = null;
	private $waitFn;
    private $isWaitRequired = false;

    private $handlers = [];

    private $requiredCancelRequests = 0;

    public function __construct( ...$resolverCanceller)
    {
		$callResolver = isset($resolverCanceller[0]) ? $resolverCanceller[0] : null;		
		$childLoop = $this->isEventLoopAvailable($callResolver) ? $callResolver : null;
		$callResolver = $this->isEventLoopAvailable($callResolver) ? null : $callResolver;	
		
		$callCanceller = isset($resolverCanceller[1]) ? $resolverCanceller[1] : null;
		$childLoop = $this->isEventLoopAvailable($callCanceller) ? $callCanceller : $childLoop;
		$callCanceller = $this->isEventLoopAvailable($callCanceller) ? null : $callCanceller;	
				
		$loop = isset($resolverCanceller[2]) ? $resolverCanceller[2] : self::$loop;		
		$childLoop = $this->isEventLoopAvailable($loop) ? $loop : $childLoop;
		self::$loop = $this->isEventLoopAvailable($childLoop) ? $childLoop : new Queue();
		
		$this->waitFn = is_callable($callResolver) ? $callResolver : null;
        $this->canceller = is_callable($callCanceller) ? $callCanceller : null;
			
		if (is_callable($callResolver) && !$this->isWaitRequired) {
			$this->call($callResolver);
		}
    }

	private function isEventLoopAvailable($instance = null): bool
	{
		$isInstanceiable = false;
		if ($instance instanceof TaskQueueInterface)
			$isInstanceiable = true;
		elseif ($instance instanceof LoopInterface)
			$isInstanceiable = true;
		elseif ($instance instanceof Queue)
			$isInstanceiable = true;
		elseif ($instance instanceof Loop)
			$isInstanceiable = true;
			
		return $isInstanceiable;
	}
	
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            return $this->result->then($onFulfilled, $onRejected);
        }

        if (null === $this->canceller) {
            return new static($this->resolver($onFulfilled, $onRejected));
        }

        $this->requiredCancelRequests++;

        return new static($this->resolver($onFulfilled, $onRejected), function () {
            $this->requiredCancelRequests--;

            if ($this->requiredCancelRequests <= 0) {
                $this->cancel();
            }
        });
    }

    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            return $this->result->done($onFulfilled, $onRejected);
        }

        $this->handlers[] = function (PromiseInterface $promise) use ($onFulfilled, $onRejected) {
            $promise
                ->done($onFulfilled, $onRejected);
        };
    }

    public function otherwise(callable $onRejected)
    {
        return $this->then(null, function ($reason) use ($onRejected) {
            if (!_checkTypehint($onRejected, $reason)) {
                return new RejectedPromise($reason);
            }

            return $onRejected($reason);
        });
    }

    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(function ($value) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected())->then(function () use ($value) {
                return $value;
            });
        }, function ($reason) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected())->then(function () use ($reason) {
                return new RejectedPromise($reason);
            });
        });
    }

    public function cancel()
    {
        $canceller = $this->canceller;
        $this->canceller = null;

        $parentCanceller = null;

        if (null !== $this->result) {
            // Go up the promise chain and reach the top most promise which is
            // itself not following another promise
            $root = $this->unwrap($this->result);

            // Return if the root promise is already resolved or a
            // FulfilledPromise or RejectedPromise
            if (!$root instanceof self || null !== $root->result) {
                return;
            }

            $root->requiredCancelRequests--;

            if ($root->requiredCancelRequests <= 0) {
                $parentCanceller = [$root, 'cancel'];
            }
        }

        if (null !== $canceller) {
            $this->call($canceller);
        }

        // For BC, we call the parent canceller after our own canceller
        if ($parentCanceller) {
            $parentCanceller();
        }
    }

    private function resolver(callable $onFulfilled = null, callable $onRejected = null)
    {
        return function ($resolve, $reject) use ($onFulfilled, $onRejected) {
            $this->handlers[] = function (PromiseInterface $promise) use ($onFulfilled, $onRejected, $resolve, $reject) {
                $promise
                    ->then($onFulfilled, $onRejected)
                    ->done($resolve, $reject);
            };
        };
    }

    private function resolve($value = null)
    {
        if (null !== $this->result) {
            return;
        }

        $this->settle(resolve($value));
    }

    private function reject($reason = null)
    {
        if (null !== $this->result) {
            return;
        }

        $this->settle(reject($reason));
    }

    private function settle(PromiseInterface $result)
    {
        $result = $this->unwrap($result);

        if ($result === $this) {
            $result = new RejectedPromise(
                new \LogicException('Cannot resolve a promise with itself.')
            );
        }

        if ($result instanceof self) {
            $result->requiredCancelRequests++;
        } else {
            // Unset canceller only when not following a pending promise
            $this->canceller = null;
        }

        $handlers = $this->handlers;

        $this->handlers = [];
        $this->result = $result;

        foreach ($handlers as $handler) {
            $handler($result);
        }
    }

    private function unwrap($promise)
    {
        while ($promise instanceof self && null !== $promise->result) {
            $promise = $promise->result;
        }

        return $promise;
    }

    private function call(callable $callback)
    {
        // Use reflection to inspect number of arguments expected by this callback.
        // We did some careful benchmarking here: Using reflection to avoid unneeded
        // function arguments is actually faster than blindly passing them.
        // Also, this helps avoiding unnecessary function arguments in the call stack
        // if the callback creates an Exception (creating garbage cycles).
        if (is_array($callback)) {
            $ref = new \ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_object($callback) && !$callback instanceof \Closure) {
            $ref = new \ReflectionMethod($callback, '__invoke');
        } else {
            $ref = new \ReflectionFunction($callback);
        }
        $args = $ref->getNumberOfParameters();

        try {
            if ($args === 0) {
                $callback();
            } else {
                $callback(
                    function ($value = null) {
                        $this->resolve($value);
                    },
                    function ($reason = null) {
                        $this->reject($reason);
                    }
                );
            }
        } catch (\Throwable $e) {
            $this->reject($e);
        } catch (\Exception $e) {
            $this->reject($e);
        }
    }
	
    public function getLoop()
    {		
        return self::$loop;
    }	
	
    /**
     * Returns the promise state.
     *
     *  'pending'	The promise is still open
     *  'fulfilled'	The promise completed successfully
     *  'rejected'	The promise failed
     *
     * @return string A promise state
     */
	public function getState()
	{
		if ($this->isPending())
			$this->state = 'pending';
		elseif ($this->isFulfilled())
			$this->state = 'fulfilled';
		elseif ($this->isRejected())
			$this->state = 'rejected';
		
		return $this->state;
	}
	
    /**
     * @override
     * @inheritDoc
     */
    public function isPending()
    {
        return $this->result === null;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isFulfilled()
    {
        return !$this->isPending() && $this->result->isFulfilled();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isRejected()
    {
        return !$this->isPending() && $this->result->isRejected();
    }
	
	public function implement(callable $function, PromiseInterface $promise = null)
	{		
        if (self::$loop) {
			$loop = self::$loop;
			
			$othersLoop = method_exists($loop, 'futureTick') ? [$loop, 'futureTick'] : null;
			$othersLoop = method_exists($loop, 'nextTick') ? [$loop, 'nextTick'] : $othersLoop;
			$othersLoop = method_exists($loop, 'addTick') ? [$loop, 'addTick'] : $othersLoop;
			$othersLoop = method_exists($loop, 'onTick') ? [$loop, 'onTick'] : $othersLoop;
			$othersLoop = method_exists($loop, 'add') ? [$loop, 'add'] : $othersLoop;
			
			if ($othersLoop)
				call_user_func_array($othersLoop, $function); 
			else 	
				$loop->enqueue($function);
        } else {
            return $function();
        } 
		
		return $promise;
	}
}
