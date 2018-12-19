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
class RejectedPromiseTest extends TestCase
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
	
    public function testThrowsReasonWhenWaitedUpon()
    {
        $p = new RejectedPromise('foo');
        $this->assertTrue($p->isRejected());
        try {
            $p->wait(true);
            $this->fail();
        } catch (\Exception $e) {
            $this->assertTrue($p->isRejected());
            $this->assertContains('foo', $e->getMessage());
        }
    }

    public function testCannotCancel()
    {
        $p = new RejectedPromise('foo');
        $p->cancel();
        $this->assertTrue($p->isRejected());
    }

    public function testReturnsSelfWhenNoOnReject()
    {
        $p = new RejectedPromise('a');
        $this->assertSame($p, $p->then());
    }

    public function testInvokesOnRejectedAsynchronously()
    {
        $p = new RejectedPromise('a');
        $r = null;
        $f = function ($reason) use (&$r) { $r = $reason; };
        $p->then(null, $f);
        //$this->assertNull($r);
        //$this->loop->run();
        $this->assertEquals('a', $r);
    }

    public function testReturnsNewRejectedWhenOnRejectedFails()
    {
        $p = new RejectedPromise('a');
        $f = function () { throw new \Exception('b'); };
        $p2 = $p->then(null, $f);
        $this->assertNotSame($p, $p2);
        try {
            $p2->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('b', $e->getMessage());
        }
    }

    public function testWaitingIsNoOp()
    {
        $p = new RejectedPromise('a');
        $this->assertNull($p->wait(false));
    }

    public function testOtherwiseIsSugarForRejections()
    {
        $p = new RejectedPromise('foo');
        $p->otherwise(function ($v) use (&$c) { $c = $v; });
        $this->loop->run();
        $this->assertSame('foo', $c);
    }

    public function testCanResolveThenWithSuccess()
    {
        $actual = null;
        $p = new RejectedPromise('foo');
        $p->otherwise(function ($v) {
            return $v . ' bar';
        })->then(function ($v) use (&$actual) {
            $actual = $v;
        });
        $this->loop->run();
        $this->assertEquals('foo bar', $actual);
    }
}
