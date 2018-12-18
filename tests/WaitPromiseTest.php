<?php

namespace React\Promise;
//namespace Async\Tests;
//namespace Sabre\Event\Promise;
//namespace GuzzleHttp\Promise\Tests;


use Exception;
//use Sabre\Event\Loop;
//use Sabre\Event\Promise;
//use Sabre\Event\RejectionException;
//use Sabre\Event\CancellationException;
//use Sabre\Event\PromiseAlreadyResolvedException;
//use GuzzleHttp\Promise\Promise;
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

class WaitPromiseTest extends TestCase
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
		//$this->markTestSkipped('These tests fails currently because of no wait() method, taken from Guzzle and Sabra phpunit tests.');
		//Loop::clearInstance();
		//Promise::clearLoop();
		//$this->loop = Promise::getLoop(true);
		//$this->loop = Loop\instance();
		//$this->loop = \GuzzleHttp\Promise\queue();
		$this->loop = Factory::create();
		//$this->loop = new CancellationQueue();
    }
	
    public function testWaitResolve()
    {
        $promise = new Promise();
        //$this->loop->nextTick(function () use ($promise) {
        //$this->loop->addTick(function () use ($promise) {
        //$this->loop->futureTick(function () use ($promise) {
        //$this->loop->add(function () use ($promise) {
        enqueue(function () use ($promise) {
            $promise->resolve(1);
        });
        $this->assertEquals(
            1,
            $promise->wait()
        );
    }
		
    public function testWaitRejectedException()
    {
        $promise = new Promise();
        //$this->loop->nextTick(function () use ($promise) {
        //$this->loop->addTick(function () use ($promise) {
        //$this->loop->futureTick(function () use ($promise) {
        //$this->loop->add(function () use ($promise) {
        enqueue(function () use ($promise) {
            $promise->reject(new \OutOfBoundsException('foo'));
        });
        try {
            $promise->wait();
            $this->fail('We did not get the expected exception');
        } catch (\Exception $e) {
            $this->assertInstanceOf('OutOfBoundsException', $e);
            $this->assertEquals('foo', $e->getMessage());
        }
    }

    public function testWaitRejectedScalar()
    {
        $promise = new Promise();
        //$this->loop->nextTick(function () use ($promise) {
        //$this->loop->addTick(function () use ($promise) {
        //$this->loop->futureTick(function () use ($promise) {
        //$this->loop->add(function () use ($promise) {
        enqueue(function () use ($promise) {
            $promise->reject(new Exception('foo'));
        });
        try {
            $promise->wait();
            $this->fail('We did not get the expected exception');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Exception', $e);
            $this->assertEquals('foo', $e->getMessage());
        }
    }	
		
    public function testRejectsPromiseWhenCancelFails()
    {
        $called = false;
        $p = new Promise(null, function () use (&$called) {
            $called = true;
            throw new \Exception('e');
        });
        $p->cancel();
        $this->assertEquals(self::REJECTED, $p->getState());
        $this->assertTrue($called);
        try {
            $p->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('e', $e->getMessage());
        }
    }	
		
    /**
     * @expectedException \Exception
     * @expectedExceptionMessage foo
     */
    public function testThrowsWhenUnwrapIsRejectedWithNonException()
    {
        $p = new Promise(function () use (&$p) { $p->reject('foo'); });
        $p->wait();
    }
	
    /**
     * @expectedException \Exception
     * @expectedExceptionMessage foo
     */
    public function testThrowsWhenUnwrapIsRejectedWithException()
    {
        $e = new \Exception('foo');
        $p = new Promise(function () use (&$p, $e) { $p->reject($e); });
        $p->wait();
    }
		
    public function testInvokesWaitFunction()
    {
        $p = new Promise(function () use (&$p) { $p->resolve('10'); });
        $this->assertEquals('10', $p->wait());
    }	
	
    public function testDoesNotUnwrapExceptionsWhenDisabled()
    {
        $p = new Promise(function () use (&$p) { $p->reject('foo'); });
        $this->assertEquals(self::PENDING, $p->getState());
        $p->wait(false);
        $this->assertEquals(self::REJECTED, $p->getState());
    }
	
    public function testRejectsSelfWhenWaitThrows()
    {
        $e = new \Exception('foo');
        $p = new Promise(function () use ($e) { throw $e; });
        try {
            $p->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals(self::REJECTED, $p->getState());
        }
    }
	
    public function testThrowsWaitExceptionAfterPromiseIsResolved()
    {
        $p = new Promise(function () use (&$p) {
            $p->reject('Foo!');
            throw new \Exception('Bar?');
        });
        try {
            $p->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('Bar?', $e->getMessage());
        }
    }
	
    public function testRemovesReferenceFromChildWhenParentWaitedUpon()
    {
        $r = null;
        $p = new Promise(function () use (&$p) { $p->resolve('a'); });
        $p2 = new Promise(function () use (&$p2) { $p2->resolve('b'); });
        $pb = $p->then(
            function ($v) use ($p2, &$r) {
                $r = $v;
                return $p2;
            })
            ->then(function ($v) { return $v . '.'; });
        $this->assertEquals('a', $p->wait());
        $this->assertEquals('b', $p2->wait());
        $this->assertEquals('b.', $pb->wait());
        $this->assertEquals('a', $r);
    }
	
    public function testWaitsOnNestedPromises()
    {		
        $p = new Promise(function () use (&$p) { $p->resolve('_'); });
        $p2 = new Promise(function () use (&$p2) { $p2->resolve('foo'); });
        $p3 = $p->then(function () use ($p2) { return $p2; });
        $this->assertSame('foo', $p3->wait());
    }	
		
    public function testWaitsOnAPromiseChainEvenWhenNotUnwrapped()
    {		
        $p2 = new Promise(function () use (&$p2) {
            $p2->reject('Fail');
        });
        $p = new Promise(function () use ($p2, &$p) {
            $p->resolve($p2);
        });
        $p->wait(false);
        $this->assertSame(self::REJECTED, $p2->getState());
    }
		
    public function testInvokesWaitFnsForThens()
    {		
        $p = new Promise(function () use (&$p) { $p->resolve('a'); });
        $p2 = $p
            ->then(function ($v) { return $v . '-1-'; })
            ->then(function ($v) { return $v . '2'; });
        $this->assertEquals('a-1-2', $p2->wait());
    }
	
    public function testStacksThenWaitFunctions()
    {		
        $p1 = new Promise(function () use (&$p1) { $p1->resolve('a'); });
        $p2 = new Promise(function () use (&$p2) { $p2->resolve('b'); });
        $p3 = new Promise(function () use (&$p3) { $p3->resolve('c'); });
        $p4 = $p1
            ->then(function () use ($p2) { return $p2; })
            ->then(function () use ($p3) { return $p3; });
        $this->assertEquals('c', $p4->wait());
    }
	
    public function testDoesNotBlowStackWhenWaitingOnNestedThens()
    {
        $inner = new Promise(function () use (&$inner) { $inner->resolve(0); });
        $prev = $inner;
        for ($i = 1; $i < 100; $i++) {
            $prev = $prev->then(function ($i) { return $i + 1; });
        }
        $parent = new Promise(function () use (&$parent, $prev) {
            $parent->resolve($prev);
        });
        $this->assertEquals(99, $parent->wait());
    }	
}
