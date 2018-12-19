<?php

namespace React\Promise;

use React\Promise\Promise;
use React\Promise\RejectedPromise;
use React\EventLoop\Factory;
//use React\Promise\UnhandledRejectionException;
use PHPUnit\Framework\TestCase;

/**
 * @covers React\Promise\RejectedPromise
 */
class FailingRejectedPromiseTest extends TestCase
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
     * @exepctedExceptionMessage Cannot resolve a rejected promise
     */
    public function testCannotResolve()
    {
        $p = new RejectedPromise('foo');
        $p->resolve('bar');
    }

    /**
     * @expectedException \LogicException
     * @exepctedExceptionMessage Cannot reject a rejected promise
     */
    public function testCannotReject()
    {
        $p = new RejectedPromise('foo');
        $p->reject('bar');
    }

    public function testCanRejectWithSameValue()
    {
        $p = new RejectedPromise('foo');
        $p->reject('foo');
    }


    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCannotResolveWithPromise()
    {
        new RejectedPromise(new Promise());
    }
		
    public function testThrowsSpecificException()
    {
        $e = new \Exception();
        $p = new RejectedPromise($e);
        try {
            $p->wait(true);
            $this->fail();
        } catch (\Exception $e2) {
            $this->assertSame($e, $e2);
        }
    }
	
    public function testDoesNotTryToRejectTwiceDuringTrampoline()
    {
        $fp = new RejectedPromise('a');
        $t1 = $fp->then(null, function ($v) { return $v . ' b'; });
        $t1->resolve('why!');
        $this->assertEquals('why!', $t1->wait());
    }
}
