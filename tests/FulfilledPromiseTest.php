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
class FulfilledPromiseTest extends TestCase
{
	private $loop = null;
	
	protected function setUp()
    {
		//Loop::clearInstance();
		//Promise::clearLoop();
		//$this->loop = Promise::getLoop(true);
		//$this->loop = Loop\instance();
		//$this->loop = \GuzzleHttp\Promise\queue();
		$this->loop = Factory::create();
		//$this->loop = new CancellationQueue();
    }
	
    public function testReturnsValueWhenWaitedUpon()
    {
        $p = new FulfilledPromise('foo');
        $this->assertTrue($p->isFulfilled());
        $this->assertEquals('foo', $p->wait(true));
    }

    public function testCannotCancel()
    {
        $p = new FulfilledPromise('foo');
        $this->assertTrue($p->isFulfilled());
        $p->cancel();
        $this->assertEquals('foo', $p->wait());
    }

    public function testReturnsSelfWhenNoOnFulfilled()
    {
        $p = new FulfilledPromise('a');
        $this->assertSame($p, $p->then());
    }

    public function testAsynchronouslyInvokesOnFulfilled()
    {
        $p = new FulfilledPromise('a');
        $r = null;
        $f = function ($d) use (&$r) { $r = $d; };
        $p2 = $p->then($f);
        $this->assertNotSame($p, $p2);
        //$this->assertNull($r);
        //$this->loop->run();
        $this->assertEquals('a', $r);
    }

    public function testReturnsNewRejectedWhenOnFulfilledFails()
    {
        $p = new FulfilledPromise('a');
        $f = function () { throw new \Exception('b'); };
        $p2 = $p->then($f);
        $this->assertNotSame($p, $p2);
        try {
            $p2->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('b', $e->getMessage());
        }
    }

    public function testOtherwiseIsSugarForRejections()
    {
        $c = null;
        $p = new FulfilledPromise('foo');
        $p->otherwise(function ($v) use (&$c) { $c = $v; });
        $this->assertNull($c);
    }
}
