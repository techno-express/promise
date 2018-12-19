<?php

namespace React\Promise;

//use React\Promise\Internal\Queue;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Exception\LogicException;

final class Promise implements PromiseInterface
{
	const PENDING = 'pending';
	const REJECTED = 'rejected';	
	const FULFILLED = 'fulfilled';
	
    private $canceller;

    /**
     * @var PromiseInterface
     */
    private $result;

    private $handlers = [];

    private $requiredCancelRequests = 0;
    private $isCancelled = false;
	
    private $state = self::PENDING;
    private static $loop = null;
	private $waitFunction;
    private $isWaitRequired = false;
    private $value;

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
		self::$loop = $this->isEventLoopAvailable($childLoop) ? $childLoop : Factory::create();
		
        $this->canceller = is_callable($callCanceller) ? $callCanceller : null;	
		
		/**
		* The difference in Guzzle promise implementations mainly lay in the construction.
		*
		* According to https://github.com/promises-aplus/constructor-spec/issues/18 it's not valid. 
		* The constructor starts the fate of the promise. Which has to been delayed, under Guzzle. 
		*
		* The wait method is necessary in certain callable functions situations. 
		* The constructor will fail it's execution when trying to access member method that's null. 
		* It will happen when passing an promise object not fully created, itself.
		*
		* Normally an promise is attached to an external running event loop, no need to start the process.
		* The wait function/method both starts and stops it, internally.
		*/
		$this->waitFunction = is_callable($callResolver) ? $callResolver : null;
		
		$promiseFunction = function () use($callResolver) { 
			if (is_callable($callResolver)) {
				$callResolver(
					[$this, 'resolve'],
					[$this, 'reject']
				);
			}			
		};
			
		//if (is_callable($callResolver) && !$this->isWaitRequired) {
		//	$this->call($callResolver);
		//}
		try {
			$promiseFunction();
		} catch (\Throwable $e) {
			$this->isWaitRequired = true;
			$this->implement($promiseFunction);
		} catch (\Exception $exception) {
			$this->isWaitRequired = true;
			$this->implement($promiseFunction);
		}	
    }

	private function isEventLoopAvailable($instance = null): bool
	{
		$isInstantiable = false;
		if ($instance instanceof TaskQueueInterface)
			$isInstantiable = true;
		elseif ($instance instanceof LoopInterface)
			$isInstantiable = true;
		elseif ($instance instanceof Queue)
			$isInstantiable = true;
		elseif ($instance instanceof Loop)
			$isInstantiable = true;
			
		return $isInstantiable;
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

        $this->isCancelled = true;

        if (null !== $canceller) {
            $this->call($canceller);
        }

        // For BC, we call the parent canceller after our own canceller
        if ($parentCanceller) {
            $parentCanceller();
        }
    }

    public function isFulfilled()
    {
        if (null !== $this->result) {
            return $this->result->isFulfilled();
        }

        return false;
    }

    public function isRejected()
    {
        if (null !== $this->result) {
            return $this->result->isRejected();
        }

        return false;
    }

    public function isPending()
    {
        if (null !== $this->result) {
            return $this->result->isPending();
        }

        return true;
    }

    public function isCancelled()
    {
        return $this->isCancelled;
    }

    public function value()
    {
        if (null !== $this->result) {
            return $this->result->value();
        }

        throw LogicException::valueFromNonFulfilledPromise();
    }

    public function reason()
    {
        if (null !== $this->result) {
            return $this->result->reason();
        }

        throw LogicException::reasonFromNonRejectedPromise();
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

    public function resolve($value = null)
    {
        if (null !== $this->result) {
            return;
        }
		
		$this->value = $value;
        $this->settle(resolve($value));
    }

    public function reject($reason = null)
    {
        if (null !== $this->result) {
            return;
        }
		
		$this->value = $reason;
        $this->settle(reject($reason));
    }

    private function settle(PromiseInterface $result)
    {
        $result = $this->unwrap($result);

        if ($result === $this) {
            $result = new RejectedPromise(
                LogicException::circularResolution()
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
			$this->state = self::PENDING;
		elseif ($this->isFulfilled())
			$this->state = self::FULFILLED;
		elseif ($this->isRejected())
			$this->state = self::REJECTED;
		
		return $this->state;
	}
		
	public function implement(callable $function, PromiseInterface $promise = null)
	{		
        if (self::$loop) {
			$loop = self::$loop;
			
			$othersLoop = null;
			if (method_exists($loop, 'futureTick'))
				$othersLoop = [$loop, 'futureTick']; 
			elseif (method_exists($loop, 'nextTick'))
				$othersLoop = [$loop, 'nextTick'];
			elseif (method_exists($loop, 'addTick'))
				$othersLoop = [$loop, 'addTick'];
			elseif (method_exists($loop, 'onTick'))
				$othersLoop = [$loop, 'onTick'];
			elseif (method_exists($loop, 'add'))
				$othersLoop = [$loop, 'add'];
			
			if ($othersLoop)
				call_user_func($othersLoop, $function); 
			else 	
				enqueue($function);
        } else {
            return $function();
        } 
		
		return $promise;
	}
	
	/**
     * Stops execution until this promise is resolved.
     *
     * This method stops execution completely. If the promise is successful with
     * a value, this method will return this value. If the promise was
     * rejected, this method will throw an exception.
     *
     * This effectively turns the asynchronous operation into a synchronous
     * one. In PHP it might be useful to call this on the last promise in a
     * chain.
     *
     * @return mixed
     */
    public function wait($unwrap = true)
    {
		try {
			$loop = self::$loop;
			$func = $this->waitFunction;
			$this->waitFunction = null;
			if (is_callable($func) 
				&& method_exists($loop, 'add') 
				&& method_exists($loop, 'run') 
				&& $this->isWaitRequired
			) {
				$func([$this, 'resolve'], [$this, 'reject']);
				$loop->run();
			} elseif (method_exists($loop, 'run')) {
				//if (is_callable($func) && $this->isWaitRequired) 
					//$func([$this, 'resolve'], [$this, 'reject']);
				$loop->run();	
			}
        } catch (\Exception $reason) {
            if ($this->getState() === self::PENDING) {
                // The promise has not been resolved yet, so reject the promise
                // with the exception.
                $this->reject($reason);
            } else {
                // The promise was already resolved, so there's a problem in
                // the application.
                throw $reason;
            }
        }

		if ($this->value instanceof PromiseInterface) {
            return $this->value->wait($unwrap);
        }
		
		if ($this->getState() === self::PENDING) {
            $this->reject('Invoking wait did not resolve the promise');
        } elseif ($unwrap) {
			if ($this->getState() === self::FULFILLED) {
				// If the state of this promise is resolved, we can return the value.
				return $this->value();
			} 
			// If we got here, it means that the asynchronous operation
			// erred. Therefore it's rejected, so throw an exception.
			$reason = $this->reason();
			
			throw $reason instanceof \Exception
				? $reason
				: new \Exception($reason);
		}	
	}
}
