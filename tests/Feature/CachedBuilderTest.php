<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Stubs\User;
use Tests\Stubs\UserWithCustomTtl;
use Tests\Stubs\UserWithPropertyTtl;

beforeEach(function (): void {
    Cache::flush();
    User::query()->delete();
});

/**
 * Get the raw storage from the array cache store (for TTL assertions).
 * Returns array of key => ['value' => mixed, 'expiresAt' => float] where expiresAt is 0 for forever.
 *
 * @return array<string, array{value: mixed, expiresAt: float}>
 */
function getArrayCacheStorage(): array
{
    $store = Cache::getStore();

    if (! $store instanceof ArrayStore) {
        return [];
    }

    $storageProp = (new \ReflectionClass($store))->getProperty('storage');
    $storageProp->setAccessible(true);

    return $storageProp->getValue($store);
}

/**
 * Run the same query twice and assert: first call hits the database, second call is served from cache (no new queries).
 *
 * @return array{0: mixed, 1: mixed}
 */
function assertSecondCallFromCache(callable $query): array
{
    DB::connection()->enableQueryLog();

    $first = $query();
    $queryCountAfterFirst = count(DB::getQueryLog());

    $second = $query();
    $queryCountAfterSecond = count(DB::getQueryLog());

    expect($queryCountAfterFirst)->toBeGreaterThan(
        0,
        'First call should execute database queries'
    );
    expect($queryCountAfterSecond)->toBe(
        $queryCountAfterFirst,
        'Second call should not run new queries (must be served from cache)'
    );

    return [$first, $second];
}

describe('CachedBuilder', function (): void {
    describe('collections', function (): void {
        describe('get', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 1,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 2,
                ]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->get());

                expect($first)->toBe($second)
                    ->and($first)->toHaveCount(2);
            });

            it('caches empty result when the table has no rows', function (): void {
                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->get());

                expect($first)->toBe($second)
                    ->and($first)->toBeEmpty();
            });

            it('uses different cache keys for different queries so each query runs only once', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 1,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 2,
                ]);
                User::create([
                    'name' => 'C',
                    'score' => 3,
                    'active' => false,
                ]);

                DB::connection()->enableQueryLog();
                $all = User::query()->get();
                $queriesAfterAll = count(DB::getQueryLog());
                $active = User::query()
                    ->where('active', true)
                    ->get();
                $queriesAfterActive = count(DB::getQueryLog());

                expect($all)->not->toBe($active)
                    ->and($all)->toHaveCount(3)
                    ->and($active->count())->toBe(2)
                    ->and($queriesAfterAll)->toBeGreaterThan(0)
                    ->and($queriesAfterActive)->toBeGreaterThan($queriesAfterAll);

                $activeAgain = User::query()
                    ->where('active', true)
                    ->get();
                expect(count(DB::getQueryLog()))->toBe($queriesAfterActive)
                    ->and($activeAgain)->toBe($active);
            });
        });

        describe('first', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'Alice',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->first());

                expect($first)->toBe($second)
                    ->and($first->name)->toBe('Alice');
            });
        });

        describe('find', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                $user = User::create([
                    'name' => 'Bob',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->find($user->id));

                expect($first)->toBe($second)
                    ->and($first->name)->toBe('Bob');
            });

            it('caches null when find() is called with a non-existent id', function (): void {
                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->find(99999)
                );

                expect($first)->toBeNull()
                    ->and($second)->toBeNull();
            });
        });

        describe('findMany', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                $u1 = User::create([
                    'name' => 'X',
                    'score' => 0,
                ]);
                $u2 = User::create([
                    'name' => 'Y',
                    'score' => 0,
                ]);
                $ids = [$u1->id, $u2->id];

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->findMany($ids)
                );

                expect($first)->toBe($second)
                    ->and($first)->toHaveCount(2);
            });
        });

        describe('pluck', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 0,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()
                        ->orderBy('id')
                        ->pluck('name')
                );

                expect($first)->toBe($second)
                    ->and($first->values()->all())->toBe(['A', 'B']);
            });

            it('caches pluck() and get() separately so repeated calls to each are served from cache', function (): void {
                User::create([
                    'name' => 'Only',
                    'score' => 0,
                ]);

                DB::connection()->enableQueryLog();
                User::query()->get();
                $queriesAfterFirstGet = count(DB::getQueryLog());
                User::query()->get();
                $queriesAfterSecondGet = count(DB::getQueryLog());

                User::query()->pluck('name');
                $queriesAfterFirstPluck = count(DB::getQueryLog());
                User::query()->pluck('name');
                $queriesAfterSecondPluck = count(DB::getQueryLog());

                expect($queriesAfterFirstGet)->toBeGreaterThan(0)
                    ->and($queriesAfterSecondGet)->toBe($queriesAfterFirstGet)
                    ->and($queriesAfterFirstPluck)->toBeGreaterThan($queriesAfterSecondGet)
                    ->and($queriesAfterSecondPluck)->toBe($queriesAfterFirstPluck);
            });
        });

        describe('value', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'Charlie',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->value('name'));

                expect($first)->toBe($second)
                    ->and($first)->toBe('Charlie');
            });
        });

        describe('sole', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'Sole',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()
                        ->where('name', 'Sole')
                        ->sole()
                );

                expect($first)->toBe($second)
                    ->and($first->name)->toBe('Sole');
            });
        });
    });

    describe('aggregates', function (): void {
        describe('count', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 0]);
                User::create(['name' => 'B', 'score' => 0]);
                User::create(['name' => 'C', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->count()
                );

                expect($first)->toBe($second)
                    ->and($first)->toBe(3);
            });
        });

        describe('sum', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 10,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 20,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->sum('score')
                );

                expect($first)->toBe($second)
                    ->and($first)->toBe(30);
            });

            it('caches zero when no rows match the query', function (): void {
                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->sum('score')
                );

                expect($first)->toBe(0)
                    ->and($second)->toBe(0);
            });
        });

        describe('avg', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 10,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 20,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->avg('score')
                );

                expect($first)->toBe($second)
                    ->and($first)->toBe(15.0);
            });
        });

        describe('average', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 100,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->average('score')
                );

                expect($first)->toBe($second)
                    ->and($first)->toBe(100.0);
            });
        });

        describe('min', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 5,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 15,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->min('score')
                );

                expect($first)->toBe($second)
                    ->and($first)->toBe(5);
            });
        });

        describe('max', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 5,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 15,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->max('score')
                );

                expect($first)->toBe($second)
                    ->and($first)->toBe(15);
            });
        });
    });

    describe('existence', function (): void {
        describe('exists', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'X',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->exists()
                );

                expect($first)->toBe($second)
                    ->and($first)->toBeTrue();
            });

            it('caches false when no rows exist so second call is served from cache', function (): void {
                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()
                        ->where('id', 99999)
                        ->exists()
                );

                expect($first)->toBe($second)
                    ->and($first)->toBeFalse();
            });
        });

        describe('doesntExist', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()
                        ->where('id', 99999)
                        ->doesntExist()
                );

                expect($first)->toBe($second)
                    ->and($first)->toBeTrue();
            });
        });
    });

    describe('pagination', function (): void {
        describe('paginate', function (): void {
            it('caches the get result so second paginate() runs only the count query', function (): void {
                foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
                    User::create([
                        'name' => $name,
                        'score' => 0,
                    ]);
                }

                DB::connection()->enableQueryLog();
                $first = User::query()->paginate(2);
                $queriesAfterFirst = count(DB::getQueryLog());

                $second = User::query()->paginate(2);
                $queriesAfterSecond = count(DB::getQueryLog());

                expect($first->items())->toBe($second->items())
                    ->and($first->total())->toBe(5)
                    ->and($queriesAfterFirst)->toBe(2)
                    ->and($queriesAfterSecond)->toBe($queriesAfterFirst + 1);
            });
        });

        describe('simplePaginate', function (): void {
            it('runs the query on first call and serves the result from cache on second call', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 0,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 0,
                ]);
                User::create([
                    'name' => 'C',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->simplePaginate(2)
                );

                expect($first->items())->toBe($second->items());
            });
        });
    });

    describe('model', function (): void {
        describe('cache TTL', function (): void {
            it('uses the default cache TTL from the trait when the model has HasCache', function (): void {
                User::create([
                    'name' => 'Ttl',
                    'score' => 0,
                ]);

                [$first, $second] = assertSecondCallFromCache(
                    fn () => User::query()->get()
                );

                expect($first)->toBe($second)
                    ->and($first)->toHaveCount(1)
                    ->and($first->first()->name)->toBe('Ttl');
            });

            it('stores collection query result with a finite TTL within the expected range', function (): void {
                User::create([
                    'name' => 'Ttl',
                    'score' => 0,
                ]);

                User::query()->get();

                $storage = getArrayCacheStorage();
                $dataEntries = array_filter($storage, fn (array $_, string $key) => ! str_starts_with($key, 'tag:'), ARRAY_FILTER_USE_BOTH);
                expect($dataEntries)->toHaveCount(1);

                $entry = array_values($dataEntries)[0];

                expect($entry['expiresAt'])->toBeGreaterThan(0)
                    ->and($entry['expiresAt'])->toBeGreaterThan(time())
                    ->and($entry['expiresAt'])->toBeLessThanOrEqual(time() + 601);
            });

            it('uses custom TTL from cacheTtl() when overridden on the model', function (): void {
                User::create([
                    'name' => 'CustomTtl',
                    'score' => 0,
                ]);

                UserWithCustomTtl::query()->get();

                $storage = getArrayCacheStorage();
                $dataEntries = array_filter($storage, fn (array $_, string $key) => ! str_starts_with($key, 'tag:'), ARRAY_FILTER_USE_BOTH);
                expect($dataEntries)->toHaveCount(1);

                $entry = array_values($dataEntries)[0];
                $now = time();

                expect($entry['expiresAt'])->toBeGreaterThan($now + 299)
                    ->and($entry['expiresAt'])->toBeLessThanOrEqual($now + 305);
            });

            it('uses custom TTL from cacheTtl property when set on the model', function (): void {
                User::create([
                    'name' => 'PropertyTtl',
                    'score' => 0,
                ]);

                UserWithPropertyTtl::query()->get();

                $storage = getArrayCacheStorage();
                $dataEntries = array_filter($storage, fn (array $_, string $key) => ! str_starts_with($key, 'tag:'), ARRAY_FILTER_USE_BOTH);
                expect($dataEntries)->toHaveCount(1);

                $entry = array_values($dataEntries)[0];
                $now = time();

                expect($entry['expiresAt'])->toBeGreaterThan($now + 122)
                    ->and($entry['expiresAt'])->toBeLessThanOrEqual($now + 128);
            });
        });

        describe('warmup', function (): void {
            it('warmup() fills the cache so a later get() without warmup is served from cache', function (): void {
                User::create([
                    'name' => 'Warmup',
                    'score' => 0,
                ]);

                DB::connection()->enableQueryLog();
                $warmup = User::query()->warmup()->get();
                $queryCountAfterWarmup = count(DB::getQueryLog());

                $first = User::query()->get();
                $queryCountAfterFirstGet = count(DB::getQueryLog());
                $second = User::query()->get();
                $queryCountAfterSecondGet = count(DB::getQueryLog());

                expect($queryCountAfterWarmup)->toBeGreaterThan(0, 'Warmup should execute database query');
                expect($queryCountAfterFirstGet)->toBe($queryCountAfterWarmup, 'First get() should be from cache');
                expect($queryCountAfterSecondGet)->toBe($queryCountAfterWarmup, 'Second get() should be from cache');
                expect($first)->toBe($second)
                    ->and($first)->toHaveCount(1)
                    ->and($first->first()->name)->toBe('Warmup');
            });

            it('calling warmup() again refreshes the cache and returns fresh data', function (): void {
                User::create([
                    'name' => 'Warmup',
                    'score' => 0,
                ]);
                User::query()->warmup()->get();

                User::create([
                    'name' => 'WarmupTwo',
                    'score' => 1,
                ]);
                $refreshed = User::query()->warmup()->get();

                expect($refreshed)->toHaveCount(2)
                    ->and($refreshed->pluck('name')->all())->toBe(['Warmup', 'WarmupTwo']);
            });

            it('warmup() stores the result with no expiration (forever)', function (): void {
                User::create([
                    'name' => 'Warmup',
                    'score' => 0,
                ]);

                User::query()->warmup()->get();

                $storage = getArrayCacheStorage();
                $dataEntries = array_filter($storage, fn (array $_, string $key) => ! str_starts_with($key, 'tag:'), ARRAY_FILTER_USE_BOTH);
                expect($dataEntries)->toHaveCount(1);

                $entry = array_values($dataEntries)[0];
                expect($entry['expiresAt'])->toBeIn([0, 0.0]);
            });

            describe('cache invalidation', function (): void {
                it('create flushes cache so next get() hits the database and returns new data', function (): void {
                    User::create(['name' => 'First', 'score' => 0]);

                    DB::connection()->enableQueryLog();
                    User::query()->get();
                    User::query()->get();
                    $queriesAfterCached = count(DB::getQueryLog());

                    User::create(['name' => 'Second', 'score' => 1]);

                    $afterCreate = User::query()->get();
                    $queriesAfterCreate = count(DB::getQueryLog());

                    expect($queriesAfterCreate)->toBeGreaterThan($queriesAfterCached, 'Cache must be flushed on create')
                        ->and($afterCreate)->toHaveCount(2)
                        ->and($afterCreate->pluck('name')->all())->toContain('First', 'Second');
                });

                it('update flushes cache so next get() hits the database and returns updated data', function (): void {
                    $user = User::create(['name' => 'Original', 'score' => 0]);

                    DB::connection()->enableQueryLog();
                    User::query()->get();
                    User::query()->get();
                    $queriesAfterCached = count(DB::getQueryLog());

                    $user->update(['name' => 'Updated']);

                    $afterUpdate = User::query()->get();
                    $queriesAfterUpdate = count(DB::getQueryLog());

                    expect($queriesAfterUpdate)->toBeGreaterThan($queriesAfterCached, 'Cache must be flushed on update')
                        ->and($afterUpdate->first()->name)->toBe('Updated');
                });

                it('delete flushes cache so next get() hits the database and returns fresh data', function (): void {
                    $user = User::create(['name' => 'ToDelete', 'score' => 0]);
                    User::create(['name' => 'Kept', 'score' => 1]);

                    DB::connection()->enableQueryLog();
                    User::query()->get();
                    User::query()->get();
                    $queriesAfterCached = count(DB::getQueryLog());

                    $user->delete();

                    $afterDelete = User::query()->get();
                    $queriesAfterDelete = count(DB::getQueryLog());

                    expect($queriesAfterDelete)->toBeGreaterThan($queriesAfterCached, 'Cache must be flushed on delete')
                        ->and($afterDelete)->toHaveCount(1)
                        ->and($afterDelete->first()->name)->toBe('Kept');
                });
            });

            it('warmup()->count() fills the cache so later count() calls are served from cache', function (): void {
                User::create([
                    'name' => 'A',
                    'score' => 0,
                ]);
                User::create([
                    'name' => 'B',
                    'score' => 0,
                ]);

                DB::connection()->enableQueryLog();
                $warmupCount = User::query()->warmup()->count();
                $queryCountAfterWarmup = count(DB::getQueryLog());

                $first = User::query()->count();
                $second = User::query()->count();

                expect($warmupCount)->toBe(2)
                    ->and($first)->toBe(2)
                    ->and($second)->toBe(2)
                    ->and(count(DB::getQueryLog()))->toBe($queryCountAfterWarmup);
            });
        });
    });
});
