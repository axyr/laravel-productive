<?php

declare(strict_types=1);

use Axyr\Productive\Http\CacheThrottle;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\NullThrottle;
use Axyr\Productive\Http\RateLimit;
use Axyr\Productive\Http\Request;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake(syncWithCarbon: true);
    Carbon::setTestNow(Carbon::createFromTimestamp(1_000_000_000));
    $this->cache = new Repository(new ArrayStore());
    $this->throttle = new CacheThrottle($this->cache);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lets requests through while under the token limit', function () {
    $request = new Request(Method::Get, 'tasks');

    for ($i = 0; $i < 100; $i++) {
        $this->throttle->acquire($request, 'scope');
    }

    Sleep::assertNeverSlept();
});

it('waits for the next window once the token limit is reached', function () {
    $request = new Request(Method::Get, 'tasks');
    Carbon::setTestNow(Carbon::createFromTimestamp(1_000_000_003));

    for ($i = 0; $i < 101; $i++) {
        $this->throttle->acquire($request, 'scope');
    }

    Sleep::assertSequence([Sleep::for(7)->seconds()]);
    expect(Carbon::now()->getTimestamp())->toBe(1_000_000_010);
});

it('applies extra buckets such as the reports limit', function () {
    $request = new Request(Method::Get, 'reports/time_reports', rateLimits: [RateLimit::reports()]);

    for ($i = 0; $i < 11; $i++) {
        $this->throttle->acquire($request, 'scope');
    }

    Sleep::assertSequence([Sleep::for(20)->seconds()]);
});

it('keeps separate budgets per scope', function () {
    $request = new Request(Method::Get, 'reports/time_reports', rateLimits: [RateLimit::reports()]);

    for ($i = 0; $i < 10; $i++) {
        $this->throttle->acquire($request, 'token-a');
        $this->throttle->acquire($request, 'token-b');
    }

    Sleep::assertNeverSlept();
});

it('stores counters under a scoped, windowed key that expires', function () {
    $this->throttle->acquire(new Request(Method::Get, 'tasks'), 'abc');

    expect($this->cache->get('productive:throttle:abc:token:100000000'))->toBe(1);

    Carbon::setTestNow(Carbon::createFromTimestamp(1_000_000_000 + 19));
    expect($this->cache->get('productive:throttle:abc:token:100000000'))->toBe(1);

    Carbon::setTestNow(Carbon::createFromTimestamp(1_000_000_000 + 20));
    expect($this->cache->get('productive:throttle:abc:token:100000000'))->toBeNull();
});

it('does nothing when throttling is disabled', function () {
    (new NullThrottle())->acquire(new Request(Method::Get, 'tasks'), 'scope');

    Sleep::assertNeverSlept();
});

it('fails open when the cache store cannot count', function () {
    $throttle = new CacheThrottle(new Repository(new Illuminate\Cache\NullStore()));

    for ($i = 0; $i < 150; $i++) {
        $throttle->acquire(new Request(Method::Get, 'tasks'), 'scope');
    }

    Sleep::assertNeverSlept();
});
