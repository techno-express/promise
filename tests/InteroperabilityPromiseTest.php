<?php

namespace React\Promise;
//namespace Async\Tests;
//namespace Sabre\Event\Promise;
//namespace GuzzleHttp\Promise;


use Exception;
//use Sabre\Event\Loop;
//use Sabre\Event\Promise;
//use Sabre\Event\RejectionException;
//use Sabre\Event\CancellationException;
//use Sabre\Event\PromiseAlreadyResolvedException;
//use GuzzleHttp\Promise;
//use GuzzleHttp\Promise\TaskQueue;
//use GuzzleHttp\Promise\PromiseInterface;
//use GuzzleHttp\Promise\RejectionException;
//use GuzzleHttp\Promise\CancellationException;
//use Async\Loop\Loop;
//use Async\Promise\Promise;
//use Async\Promise\PromiseInterface;
use React\Promise\Promise;
use React\EventLoop\Factory;
//use React\Promise\Internal\CancellationQueue;
use React\Promise\Deferred;
use React\Promise\UnhandledRejectionException;
use PHPUnit\Framework\TestCase;

class InteroperabilityPromiseTest extends TestCase
{			
	//const PENDING = Promise::PENDING;
	//const REJECTED = Promise::REJECTED;
	//const FULFILLED = Promise::FULFILLED;	
	//const PENDING = PromiseInterface::PENDING;
	//const REJECTED = PromiseInterface::REJECTED;
	//const FULFILLED = PromiseInterface::FULFILLED;	
	//const PENDING = PromiseInterface::STATE_PENDING;
	//const REJECTED = PromiseInterface::STATE_REJECTED;
	//const FULFILLED = PromiseInterface::STATE_RESOLVED;	
	const PENDING = 'pending';
	const REJECTED = 'rejected';	
	const FULFILLED = 'fulfilled';

	private $loop = null;
	
	protected function setUp()
    {
		//Loop::clearInstance();
		//Promise::clearLoop();
		//$this->loop = Promise::getLoop(true);
		//$this->loop = Loop\instance();
		//$this->loop = new TaskQueue();
		$this->loop = Factory::create();
		//$this->loop = new CancellationQueue();
    }
	
    public function testSuccess()
    {
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });
        $this->loop->run();

        $this->assertEquals(3, $finalValue);
    }

    public function testFailure()
    {
        $finalValue = 0;
        $promise = new Promise();
        $promise->reject(new Exception('1'));

        $promise->then(null, function ($value) use (&$finalValue) {
            $finalValue = $value->getMessage() + 2;
        });
        $this->loop->run();

        $this->assertEquals(3, $finalValue);
    }

    public function testChaining()
    {
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;

            return $finalValue;
        })->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 4;

            return $finalValue;
        });
        $this->loop->run();

        $this->assertEquals(7, $finalValue);
    }
	
    public function testPendingResult()
    {
        $finalValue = 0;
        $promise = new Promise();

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });

        $promise->resolve(4);
        $this->loop->run();

        $this->assertEquals(6, $finalValue);
    }

    public function testPendingFail()
    {
        $finalValue = 0;
        $promise = new Promise();

        $promise->otherwise(function ($value) use (&$finalValue) {
            $finalValue = $value->getMessage() + 2;
        });

        $promise->reject(new Exception('4'));
        $this->loop->run();

        $this->assertEquals(6, $finalValue);
    }
	
    public function testChainingPromises()
    {
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $subPromise = new Promise();

        $promise->then(function ($value) use ($subPromise) {
            return $subPromise;
        })->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 4;

            return $finalValue;
        });

        $subPromise->resolve(2);
        $this->loop->run();

        $this->assertEquals(6, $finalValue);
    }
	
    public function testConstructorCallResolve()
    {
        $promise = (new Promise(function ($resolve, $reject) {
            $resolve('hi');
        }))->then(function ($result) use (&$realResult) {
            $realResult = $result;
        });
        $this->loop->run();

        $this->assertEquals('hi', $realResult);
    }

    public function testConstructorCallReject()
    {
        $promise = (new Promise(function ($resolve, $reject) {
            $reject(new Exception('hi'));
        }))->then(function ($result) use (&$realResult) {
            $realResult = 'incorrect';
        })->otherwise(function ($reason) use (&$realResult) {
            $realResult = $reason->getMessage();
        });
        $this->loop->run();

        $this->assertEquals('hi', $realResult);
    }

    public function testFailureHandler()
    {
        $ok = 0;
        $promise = new Promise();
        $promise->otherwise(function ($reason) {
            $this->assertEquals('foo', $reason);
            throw new \Exception('hi');
        })->then(function () use (&$ok) {
            $ok = -1;
        }, function () use (&$ok) {
            $ok = 1;
        });

        $this->assertEquals(0, $ok);
        $promise->reject(new Exception('foo'));
        $this->loop->run();

        $this->assertEquals(1, $ok);
    }
	
    public function testForwardsRejectedPromisesDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, function ($v) use (&$r) { $r = $v; return $v . '2'; })
            ->then(function ($v) use (&$r2) { $r2 = $v; });
        $p->reject('foo');
        $this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertEquals('foo2', $r2);
    }
	
    public function testForwardsThrownPromisesDownChainBetweenGaps()
    {
        $e = new \Exception();
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, function ($v) use (&$r, $e) { 
                $r = $v;
                throw $e;
            })
            ->then(
                null,
                function ($v) use (&$r2) { $r2 = $v; }
            );
        $p->reject('foo');
        $this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertSame($e, $r2);
    }
	
    public function testForwardsHandlersWhenRejectedPromiseIsReturned()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->reject('foo');
        $p2->then(null, function ($v) use (&$res) { $res[] = 'A:' . $v; });
        $p->then(null, function () use ($p2, &$res) { $res[] = 'B'; return $p2; })
            ->then(null, function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->reject('a');
        $p->then(null, function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }
	
    public function testForwardsFulfilledDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(function ($v) use (&$r) {$r = $v; return $v . '2'; })
            ->then(function ($v) use (&$r2) { $r2 = $v; });
        $p->resolve('foo');
        $this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertEquals('foo2', $r2);
    }
	
    public function testForwardsHandlersToNextPromise()
    {		
        $p = new Promise();
        $p2 = new Promise();
        $resolved = null;
        $p
            ->then(function ($v) use ($p2) { return $p2; })
            ->then(function ($value) use (&$resolved) { $resolved = $value; });
        $p->resolve('a');
        $p2->resolve('b');
        $this->loop->run();
        $this->assertEquals('b', $resolved);
    }
	
    public function testForwardsHandlersWhenFulfilledPromiseIsReturned()
    {		
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->resolve('foo');
        $p2->then(function ($v) use (&$res) { $res[] = 'A:' . $v; });
        // $res is A:foo
        $p
            ->then(function () use ($p2, &$res) { $res[] = 'B'; return $p2; })
            ->then(function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->resolve('a');
        $p->then(function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }	
	
    public function testDoesNotForwardRejectedPromise()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->cancel();
        $p2->then(function ($v) use (&$res) { $res[] = "B:$v"; return $v; });
        $p->then(function ($v) use ($p2, &$res) { $res[] = "B:$v"; return $p2; })
            ->then(function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->resolve('a');
        $p->then(function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['B:a', 'D:a'], $res);
    }
		
    /**
     * @expectedException \LogicException
	 * /expectedExceptionMessage The promise is already resolved
     * /expectedException \Sabre\Event\PromiseAlreadyResolvedException
     * /expectedExceptionMessage This promise is already resolved, and you're not allowed to resolve a promise more than once
     */
    public function testCannotResolveNonPendingPromise()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p->resolve('bar');
        $this->assertEquals('foo', $p->wait());
    }	
	
    /**
     * @expectedException \LogicException
     * /expectedExceptionMessage Cannot change a resolved promise to rejected
     * /expectedException \Sabre\Event\PromiseAlreadyResolvedException
     * /expectedExceptionMessage This promise is already resolved, and you're not allowed to resolve a promise more than once
     */
    public function testCannotRejectNonPendingPromise()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p->reject('bar');
        $this->assertEquals('foo', $p->wait());
    }
	
    /**
     * @doesNotPerformAssertions
	 * /expected Risky - No Tests Performed!
     * /expectedException \Sabre\Event\PromiseAlreadyResolvedException
     * /expectedExceptionMessage This promise is already resolved, and you're not allowed to resolve a promise more than once
     */
    public function testCanResolveWithSameValue()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p->resolve('foo');
    }

    /**
     * @doesNotPerformAssertions
	 * /expected Risky - No Tests Performed!
     * /expectedException \Sabre\Event\PromiseAlreadyResolvedException
     * /expectedExceptionMessage This promise is already resolved, and you're not allowed to resolve a promise more than once
     */
    public function testCanRejectWithSameValue()
    {
        $p = new Promise();
        $p->reject('foo');
        $p->reject('foo');
    }
	
    /**
     * @expectedException \LogicException
     * /expectedExceptionMessage Cannot change a resolved promise to rejected
     * /expectedException \Sabre\Event\PromiseAlreadyResolvedException
     * /expectedExceptionMessage This promise is already resolved, and you're not allowed to resolve a promise more than once
     */
    public function testCannotRejectResolveWithSameValue()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p->reject('foo');
    }
		
    public function testCannotCancelNonPending()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p->cancel();
        $this->assertEquals(self::FULFILLED, $p->getState());
    }
	
    public function testCancelsPromiseWithCancelFunction()
    {
        $called = false;
        $p = new Promise(null, function () use (&$called) { $called = true; });
        $p->cancel();
        $this->assertEquals(self::REJECTED, $p->getState());
        $this->assertTrue($called);
    }
	
    public function testCancelsChildPromises()
    {
        $called1 = $called2 = $called3 = false;
        $p1 = new Promise(null, function () use (&$called1) { $called1 = true; });
        $p2 = new Promise(null, function () use (&$called2) { $called2 = true; });
        $p3 = new Promise(null, function () use (&$called3) { $called3 = true; });
        $p4 = $p2->then(function () use ($p3) { return $p3; });
        $p5 = $p4->then(function () { $this->fail(); });
        $p4->cancel();
        $this->assertEquals(self::PENDING, $p1->getState());
        $this->assertEquals(self::REJECTED, $p2->getState());
        $this->assertEquals(self::REJECTED, $p4->getState());
        $this->assertEquals(self::PENDING, $p5->getState());
        $this->assertFalse($called1);
        $this->assertTrue($called2);
        $this->assertFalse($called3);
    }	
}
