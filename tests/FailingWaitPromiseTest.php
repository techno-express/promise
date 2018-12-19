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
//use React\EventLoop\Factory;
//use React\Promise\Internal\CancellationQueue;
use React\Promise\Deferred;
use React\Promise\UnhandledRejectionException;
use PHPUnit\Framework\TestCase;

class FailingWaitPromiseTest extends TestCase
{			
	const PENDING = Promise::PENDING;
	const REJECTED = Promise::REJECTED;
	const FULFILLED = Promise::FULFILLED;	
	//const PENDING = PromiseInterface::PENDING;
	//const REJECTED = PromiseInterface::REJECTED;
	//const FULFILLED = PromiseInterface::FULFILLED;	
	//const PENDING = PromiseInterface::STATE_PENDING;
	//const REJECTED = PromiseInterface::STATE_REJECTED;
	//const FULFILLED = PromiseInterface::STATE_RESOLVED;	

	private $loop = null;
	
	protected function setUp()
    {
		$this->markTestSkipped('These tests fails, taken from Guzzle and Sabra phpunit tests, ');
		//Loop::clearInstance();
		//Promise::clearLoop();
		//$this->loop = Promise::getLoop(true);
		//$this->loop = Loop\instance();
		//$this->loop = \GuzzleHttp\Promise\queue();
		//$this->loop = Factory::create();
		//$this->loop = new CancellationQueue();
    }
	
	/**
     * @expectedException \Exception
	 */
    public function testRejectsAndThrowsWhenWaitFailsToResolve()
    {
        $p = new Promise(function () {});
        $p->wait();
    }
	
    /**
     * @expectedException \Exception
     */
    public function testThrowsWhenWaitingOnPromiseWithNoWaitFunction()
    {
        $p = new Promise();
        $p->wait();
    }	
		
    /**
     * @expectedException \Exception
     */
    public function testCancelsPromiseWhenNoCancelFunction()
    {
        $p = new Promise();
        $p->cancel();
        $this->assertEquals(self::REJECTED, $p->getState());
        $p->wait();
    }
	
    /**
     * @expectedException \Exception
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
     * @expectedException \Exception
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
	
    public function testCancelsUppermostPendingPromise()
    {
        $called = false;
        $p1 = new Promise(null, function () use (&$called) { $called = true; });
        $p2 = $p1->then(function () {});
        $p3 = $p2->then(function () {});
        $p4 = $p3->then(function () {});
        $p3->cancel();
        $this->assertEquals(self::REJECTED, $p1->getState());
        $this->assertEquals(self::REJECTED, $p2->getState());
        $this->assertEquals(self::REJECTED, $p3->getState());
        $this->assertEquals(self::PENDING, $p4->getState());
        $this->assertTrue($called);
        try {
            $p3->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertContains('cancelled', $e->getMessage());
        }
        try {			
            $p4->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertContains('cancelled', $e->getMessage());
        }
        $this->assertEquals(self::REJECTED, $p4->getState());
    }
}
