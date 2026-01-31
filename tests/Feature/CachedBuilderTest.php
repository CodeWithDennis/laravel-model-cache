<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Stubs\User;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();
    User::query()->delete();
});

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

    expect($queryCountAfterFirst)->toBeGreaterThan(0, 'First call should execute database queries');
    expect($queryCountAfterSecond)->toBe($queryCountAfterFirst, 'Second call should not run new queries (must be served from cache)');

    return [$first, $second];
}

describe('CachedBuilder', function (): void {
    describe('collections', function (): void {
        describe('get', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 1]);
                User::create(['name' => 'B', 'score' => 2]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->get());

                expect($first)->toBe($second)
                    ->and($first)->toHaveCount(2);
            });

            it('uses different cache keys for different queries (each runs once)', function (): void {
                User::create(['name' => 'A', 'score' => 1]);
                User::create(['name' => 'B', 'score' => 2]);
                User::create(['name' => 'C', 'score' => 3, 'active' => false]);

                DB::connection()->enableQueryLog();
                $all = User::query()->get();
                $queriesAfterAll = count(DB::getQueryLog());
                $active = User::query()->where('active', true)->get();
                $queriesAfterActive = count(DB::getQueryLog());

                expect($all)->not->toBe($active)
                    ->and($all)->toHaveCount(3)
                    ->and($active->count())->toBe(2)
                    ->and($queriesAfterAll)->toBeGreaterThan(0)
                    ->and($queriesAfterActive)->toBeGreaterThan($queriesAfterAll);

                $activeAgain = User::query()->where('active', true)->get();
                expect(count(DB::getQueryLog()))->toBe($queriesAfterActive)
                    ->and($activeAgain)->toBe($active);
            });
        });

        describe('first', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'Alice', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->first());

                expect($first)->toBe($second)
                    ->and($first->name)->toBe('Alice');
            });
        });

        describe('find', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                $user = User::create(['name' => 'Bob', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->find($user->id));

                expect($first)->toBe($second)
                    ->and($first->name)->toBe('Bob');
            });
        });

        describe('findMany', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                $u1 = User::create(['name' => 'X', 'score' => 0]);
                $u2 = User::create(['name' => 'Y', 'score' => 0]);
                $ids = [$u1->id, $u2->id];

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->findMany($ids));

                expect($first)->toBe($second)
                    ->and($first)->toHaveCount(2);
            });
        });

        describe('pluck', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 0]);
                User::create(['name' => 'B', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->orderBy('id')->pluck('name'));

                expect($first)->toBe($second)
                    ->and($first->values()->all())->toBe(['A', 'B']);
            });

            it('uses different cache for pluck vs get (second call to each runs no new queries)', function (): void {
                User::create(['name' => 'Only', 'score' => 0]);

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
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'Charlie', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->value('name'));

                expect($first)->toBe($second)
                    ->and($first)->toBe('Charlie');
            });
        });

        describe('sole', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'Sole', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->where('name', 'Sole')->sole());

                expect($first)->toBe($second)
                    ->and($first->name)->toBe('Sole');
            });
        });
    });

    describe('aggregates', function (): void {
        describe('count', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 0]);
                User::create(['name' => 'B', 'score' => 0]);
                User::create(['name' => 'C', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->count());

                expect($first)->toBe($second)
                    ->and($first)->toBe(3);
            });
        });

        describe('sum', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 10]);
                User::create(['name' => 'B', 'score' => 20]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->sum('score'));

                expect($first)->toBe($second)
                    ->and($first)->toBe(30);
            });
        });

        describe('avg', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 10]);
                User::create(['name' => 'B', 'score' => 20]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->avg('score'));

                expect($first)->toBe($second)
                    ->and($first)->toBe(15.0);
            });
        });

        describe('average', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 100]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->average('score'));

                expect($first)->toBe($second)
                    ->and($first)->toBe(100.0);
            });
        });

        describe('min', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 5]);
                User::create(['name' => 'B', 'score' => 15]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->min('score'));

                expect($first)->toBe($second)
                    ->and($first)->toBe(5);
            });
        });

        describe('max', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 5]);
                User::create(['name' => 'B', 'score' => 15]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->max('score'));

                expect($first)->toBe($second)
                    ->and($first)->toBe(15);
            });
        });
    });

    describe('existence', function (): void {
        describe('exists', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'X', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->exists());

                expect($first)->toBe($second)
                    ->and($first)->toBeTrue();
            });

            it('caches non-existence (no new queries on second call)', function (): void {
                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->where('id', 99999)->exists());

                expect($first)->toBe($second)
                    ->and($first)->toBeFalse();
            });
        });

        describe('doesntExist', function (): void {
            it('runs query on first call and serves from cache on second call', function (): void {
                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->where('id', 99999)->doesntExist());

                expect($first)->toBe($second)
                    ->and($first)->toBeTrue();
            });
        });
    });

    describe('pagination', function (): void {
        describe('paginate', function (): void {
            it('caches get() result so second call runs only count query (not get)', function (): void {
                foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
                    User::create(['name' => $name, 'score' => 0]);
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
            it('runs query on first call and serves from cache on second call', function (): void {
                User::create(['name' => 'A', 'score' => 0]);
                User::create(['name' => 'B', 'score' => 0]);
                User::create(['name' => 'C', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->simplePaginate(2));

                expect($first->items())->toBe($second->items());
            });
        });
    });

    describe('model', function (): void {
        describe('cache TTL', function (): void {
            it('uses model cacheTtl when model has HasCache (second call from cache)', function (): void {
                User::create(['name' => 'Ttl', 'score' => 0]);

                [$first, $second] = assertSecondCallFromCache(fn () => User::query()->get());

                expect($first)->toBe($second)
                    ->and($first)->toHaveCount(1)
                    ->and($first->first()->name)->toBe('Ttl');
            });
        });
    });
});
