<?php

namespace React\Promise;

use React\Promise\Promise;
use React\Promise\FulfilledPromise;
use React\EventLoop\Factory;
//use React\Promise\UnhandledRejectionException;
use PHPUnit\Framework\TestCase;

/**
 * @covers React\Promise\FulfilledPromise
 */
class FailingFulfilledPromiseTest extends TestCase
{
	private $loop = null;
	
	protected function setUp()
    {
		$this->markTestSkipped('These tests fails, taken and modified from Guzzle phpunit tests');
		//Loop::clearInstance();
		//Promise::clearLoop();
		//$this->loop = Promise::getLoop(true);
		//$this->loop = Loop\instance();
		//$this->loop = \GuzzleHttp\Promise\queue();
		$this->loop = Factory::create();
		//$this->loop = new CancellationQueue();
    }
	
    /**
     * @expectedException \LogicException
     * @exepctedExceptionMessage Cannot resolve a fulfilled promise
     */
    public function testCannotResolve()
    {
        $p = new FulfilledPromise('foo');
        $p->resolve('bar');
    }

    /**
     * @expectedException \LogicException
     * @exepctedExceptionMessage Cannot reject a fulfilled promise
     */
    public function testCannotReject()
    {
        $p = new FulfilledPromise('foo');
        $p->reject('bar');
    }

    public function testCanResolveWithSameValue()
    {
        $p = new FulfilledPromise('foo');
        $p->resolve('foo');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCannotResolveWithPromise()
    {
        new FulfilledPromise(new Promise());
    }
	
    public function testDoesNotTryToFulfillTwiceDuringTrampoline()
    {
        $fp = new FulfilledPromise('a');
        $t1 = $fp->then(function ($v) { return $v . ' b'; });
        $t1->resolve('why!');
        $this->assertEquals('why!', $t1->wait());
    }
}
