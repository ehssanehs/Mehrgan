<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
 * Unit tests here still touch Eloquent models and the container (config(),
 * DB::transaction()), so they need the Laravel test case as well. Without this
 * binding they run on PHPUnit's TestCase and blow up with
 * "Target class [config] does not exist".
 */
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Route Telegram traffic to a recorder instead of the network.
 *
 * The SDK resolves BotsManager as a singleton built from config('telegram'),
 * so the fake has to be installed before the first bot call in a test.
 */
function fakeTelegram(): \Tests\Support\FakeTelegramHttpClient
{
    $fake = new \Tests\Support\FakeTelegramHttpClient();

    config(['telegram.http_client_handler' => $fake]);
    app()->forgetInstance(\Telegram\Bot\BotsManager::class);

    return $fake;
}
