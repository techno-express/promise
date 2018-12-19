<?php

namespace React\Promise;

use React\Promise\Promise;
//use React\Promise\Deferred;
//use React\Promise\FulfilledPromise;
//use React\Promise\RejectedPromise;
//use React\EventLoop\Factory;
//use React\Promise\Internal\CancellationQueue;
//use React\Promise\PromiseInterface;
//use React\Promise\UnhandledRejectionException;
use PHPUnit\Framework\TestCase;

class PromiseTest extends TestCase
{
	//private $loop; 
	protected function setUp()
    {
		//$this->loop = Factory::create();
    }		
		
    public function testSuccess()
    {		
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });
        //$this->loop->run();

        $this->assertEquals(3, $finalValue);
    }

    public function testPendingResult()
    {
        $finalValue = 0;
        $promise = new Promise();

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });

        $promise->resolve(4);
        //$this->loop->run();

        $this->assertEquals(6, $finalValue);
    }
		
    public function testCreatesPromiseWhenFulfilledAfterThen()
    {
        $p = new Promise();
        $carry = null;
        $p2 = $p->then(function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        $p->resolve('foo');
        //$this->loop->run();
        $this->assertEquals('foo', $carry);
    }	
	
    public function testCreatesPromiseWhenRejectedAfterThen()
    {
		$p = new Promise();
        $carry = null;
        $p2 = $p->then(null, function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        $p->reject('foo');
        //$this->loop->run();
        $this->assertEquals('foo', $carry);
    }	
	
    public function testOtherwiseIsSugarForRejections()
    {
        $p = new Promise();
        $p->reject('foo');
        $p->otherwise(function ($v) use (&$c) { $c = $v; });
        //$this->loop->run();
        $this->assertEquals($c, 'foo');
    }
	
    public function testForwardsFulfilledDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(function ($v) use (&$r) {$r = $v; return $v . '2'; })
            ->then(function ($v) use (&$r2) { $r2 = $v; });
        $p->resolve('foo');
        //$this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertEquals('foo2', $r2);
    }
	
    public function testForwardsRejectedPromisesDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, function ($v) use (&$r) { $r = $v; return $v . '2'; })
            ->then(function ($v) use (&$r2) { $r2 = $v; });
        $p->reject('foo');
        //$this->loop->run();
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
                null, function ($v) use (&$r2) { 
				$r2 = $v; }
            );
        $p->reject('foo');
        //$this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertSame($e, $r2);
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
        //$this->loop->run();
        $this->assertEquals('b', $resolved);
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
        //$this->loop->run();
        $this->assertEquals(['B:a', 'D:a'], $res);
    }	
    public function testCreatesPromiseWhenFulfilledBeforeThen()
    {
        $p = new Promise();
        $p->resolve('foo');
        $carry = null;
        $p2 = $p->then(function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        //$this->assertNull($carry);
        //$this->loop->run();
        $this->assertEquals('foo', $carry);
    }
	
    public function testCreatesPromiseWhenRejectedBeforeThen()
    {
        $p = new Promise();
        $p->reject('foo');
        $carry = null;
        $p2 = $p->then(null, function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        //$this->assertNull($carry);
        //$this->loop->run();
        $this->assertEquals('foo', $carry);
    }			
}
