<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\Acceptance;

use function PHPUnit\Framework\assertIsString;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

use Roadrunner\Integration\Symfony\Http\Test\Support\AcceptanceTester;

final class MiddlewareProfilerCest
{
    public function showsActualMiddleware(AcceptanceTester $tester): void
    {
        $tester->sendGet('/middleware/preview');
        $tester->seeResponseCodeIsSuccessful();
        $tester->seeResponseContains('"controller":"called"');

        $token = $tester->grabHttpHeader('X-Debug-Token');
        assertIsString($token);

        $tester->sendGet('/_profiler/' . $token . '?panel=roadrunner_http');
        $tester->seeResponseCodeIsSuccessful();
        $panel = $tester->grabResponse();
        assertStringContainsString('<title>RoadRunner</title>', $panel);
        preg_match_all('/<td>((?:fixture\.|roadrunner\.http\.)[^<]+)<\/td>/', $panel, $rows);
        assertSame([
            'fixture.global',
            'roadrunner.http.sentry_scope.middleware',
            'fixture.route_alias',
            'fixture.class',
            'fixture.action',
        ], $rows[1]);

        $tester->sendGet('/_wdt/' . $token);
        $tester->seeResponseCodeIsSuccessful();
        assertStringContainsString('<title>RoadRunner</title>', $tester->grabResponse());

        $tester->sendGet('/middleware/preview?stop=1');
        $tester->seeResponseContains('stopped');

        $token = $tester->grabHttpHeader('X-Debug-Token');
        assertIsString($token);

        $tester->sendGet('/_profiler/' . $token . '?panel=roadrunner_http');
        $tester->seeResponseCodeIsSuccessful();
        $panel = $tester->grabResponse();
        assertStringContainsString('fixture.route_alias', $panel);
        assertStringNotContainsString('fixture.class', $panel);
        assertStringNotContainsString('fixture.action', $panel);

        $tester->sendGet('/middleware/base');
        $token = $tester->grabHttpHeader('X-Debug-Token');
        assertIsString($token);

        $tester->sendGet('/_profiler/' . $token . '?panel=roadrunner_http');
        assertStringContainsString('fixture.action', $tester->grabResponse());
        assertStringNotContainsString('fixture.route_alias', $tester->grabResponse());
    }

    public function leavesPreKernelResponseWithoutProfile(AcceptanceTester $tester): void
    {
        $tester->sendGet('/middleware/pre-kernel');
        $tester->seeResponseContains('pre-kernel');
        assertSame(null, $tester->grabHttpHeader('X-Debug-Token'));
    }

    public function preservesSymfonyResponsesAndErrors(AcceptanceTester $tester): void
    {
        $tester->sendGet('/middleware/stream');
        $tester->seeResponseCodeIsSuccessful();
        $tester->seeHttpHeader('X-Middleware', 'class');
        $tester->seeResponseContains('scoped stream');

        $tester->sendGet('/middleware/error');
        $tester->seeResponseCodeIs(418);

        $tester->sendGet('/middleware/missing');
        $tester->seeResponseCodeIs(404);

        $tester->sendPost('/middleware/base');
        $tester->seeResponseCodeIs(405);

        $tester->sendGet('/middleware/base');
        $tester->seeResponseCodeIsSuccessful();
        $tester->seeResponseContains('"controller":"called"');
    }
}
