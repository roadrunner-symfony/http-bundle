<?php

declare(strict_types=1);


namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

final class HttpCest
{
    public function _before(AcceptanceTester $I): void
    {
        // Code here will be executed before each test.
    }

    public function tryToTest(AcceptanceTester $I): void
    {
        // Write your tests here. All `public` methods will be executed as tests.
    }



    public function sendEmptyRequest(AcceptanceTester $I): void
    {

    }


    public function sendTextRequest(AcceptanceTester $I): void
    {

    }



    public function sendBinaryRequest(AcceptanceTester $I): void
    {

    }
}
