<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\Acceptance;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\File\FilesystemException;

use function Amp\File\openFile;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Codeception\Attribute\DataProvider;
use Codeception\Example;
use Codeception\Module\REST;
use Exception;
use JsonStreamingParser\Exception\ParsingException;
use JsonStreamingParser\Listener\InMemoryListener;
use JsonStreamingParser\Parser;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertIsResource;
use function PHPUnit\Framework\assertTrue;

use Roadrunner\Integration\Symfony\Http\Test\Support\AcceptanceTester;
use Symfony\Component\String\ByteString;

final class HttpCest
{
    /**
     * @param Example<array{file: non-empty-string, content-type: non-empty-string}> $example
     *
     * @throws Exception
     */
    #[DataProvider('sendTextRequestDataProvider')]
    public function sendTextRequest(AcceptanceTester $tester, Example $example): void
    {
        $tester->haveHttpHeader('Content-Type', $example['content-type']);
        $tester->sendPost('/acceptTextRequest', (string) file_get_contents(codecept_data_dir($example['file'])));
        $tester->seeResponseCodeIsSuccessful();
        $tester->seeResponseJsonMatchesJsonPath('$.test');
        $tester->seeResponseJsonMatchesJsonPath('$.message');


        /** @var bool $isPassed */
        $isPassed = $tester->grabDataFromResponseByJsonPath('$.test')[0];
        /** @var non-empty-string $message */
        $message = $tester->grabDataFromResponseByJsonPath('$.message')[0];

        assertTrue($isPassed, $message);
    }


    /**
     * @return iterable<array{file: non-empty-string, content-type: non-empty-string}>
     */
    private function sendTextRequestDataProvider(): iterable /*@phpstan-ignore method.unused*/
    {
        yield ['file' => 'request.json', 'content-type' => 'application/json'];
        yield ['file' => 'request.xml', 'content-type' => 'application/xml'];
        yield ['file' => 'request.yaml', 'content-type' => 'application/yaml'];
    }



    /**
     * @param Example<array{file: non-empty-string, ext: non-empty-string, content-type: non-empty-string}> $example
     *
     * @throws Exception
     */
    #[DataProvider('sendBinaryRequestDataProvider')]
    public function sendBinaryRequest(AcceptanceTester $tester, Example $example): void
    {
        $tester->haveHttpHeader('Accept', 'application/json');
        $tester->haveHttpHeader('X-Ext', $example['ext']);

        $tester->sendPost('/acceptBinaryRequest', files: [
            'file' => [
                'name'     => $example['file'],
                'size'     => filesize(codecept_data_dir($example['file'])),
                'tmp_name' => codecept_data_dir($example['file']),
                'type'     => $example['content-type'],
                'error'    => UPLOAD_ERR_OK,
            ],
        ]);
        $tester->seeResponseCodeIsSuccessful();
        $tester->seeResponseJsonMatchesJsonPath('$.test');
        $tester->seeResponseJsonMatchesJsonPath('$.message');


        /** @var bool $isPassed */
        $isPassed = $tester->grabDataFromResponseByJsonPath('$.test')[0];
        /** @var non-empty-string $message */
        $message = $tester->grabDataFromResponseByJsonPath('$.message')[0];

        assertTrue($isPassed, $message);
    }


    /**
     * @return iterable<array{file: non-empty-string, ext: non-empty-string, content-type: non-empty-string}>
     */
    private function sendBinaryRequestDataProvider(): iterable /*@phpstan-ignore method.unused*/
    {
        yield ['file' => 'request.pdf', 'ext' => 'pdf', 'content-type' => 'application/pdf'];
        yield ['file' => 'request.doc', 'ext' => 'doc', 'content-type' => 'application/msword'];
    }



    /**
     * @param Example<array{content-type: non-empty-string, file: non-empty-string}> $example
     *
     * @throws Exception
     */
    #[DataProvider('getBinaryResponseDataProvider')]
    public function getBinaryResponse(AcceptanceTester $tester, Example $example): void
    {
        $tester->haveHttpHeader('Accept', $example['content-type']);
        $response = $tester->sendGet('/returnBinaryResponse');

        $tester->seeResponseCodeIsSuccessful();

        assertEquals(file_get_contents(codecept_data_dir($example['file'])), $response);
    }

    /**
     * @return iterable<array{file: non-empty-string, content-type: non-empty-string}>
     */
    private function getBinaryResponseDataProvider(): iterable /*@phpstan-ignore method.unused*/
    {
        yield ['content-type' => 'application/pdf', 'file' => 'request.pdf'];
        yield ['content-type' => 'application/msword', 'file' => 'request.doc'];
    }


    /**
     * @param Example<array{file: non-empty-string, content-type: non-empty-string}> $example
     *
     * @throws Exception
     */
    #[DataProvider('getTextResponseDataProvider')]
    public function getTextResponse(AcceptanceTester $tester, Example $example): void
    {
        $tester->haveHttpHeader('Accept', $example['content-type']);
        $response = $tester->sendGet('/returnTextResponse');

        $tester->seeResponseCodeIsSuccessful();

        assertEquals(file_get_contents(codecept_data_dir($example['file'])), $response);
    }


    /**
     * @return iterable<array{file: non-empty-string, content-type: non-empty-string}>
     */
    private function getTextResponseDataProvider(): iterable /*@phpstan-ignore method.unused*/
    {
        yield ['file' => 'request.json', 'content-type' => 'application/json'];
        yield ['file' => 'request.xml', 'content-type' => 'application/xml'];
        yield ['file' => 'request.yaml', 'content-type' => 'application/yaml'];
    }


    /**
     * @throws HttpException
     * @throws StreamException
     */
    public function getRoadRunnerStreamedResponse(REST $rest): void
    {
        $this->getStreamedResponseTest($rest, '/returnStreamingResponse');
    }


    /**
     * @throws HttpException
     * @throws StreamException
     */
    public function getOriginalStreamedResponse(REST $rest): void
    {
        $this->getStreamedResponseTest($rest, '/returnOriginalStreamingResponse');
    }



    /**
     * @param non-empty-string $uri
     *
     * @throws HttpException
     * @throws StreamException
     */
    private function getStreamedResponseTest(REST $rest, string $uri): void
    {
        /** @var non-empty-string $host */
        $host = $rest->_getConfig('url');

        $client   = HttpClientBuilder::buildDefault();
        $request  = new Request($host . $uri);
        $response = $client->request($request);

        $count = 0;

        while (null !== $response->getBody()->read()) {
            $count++;
        }

        assertTrue($count > 1500);
    }


    /**
     * @throws ClosedException
     * @throws FilesystemException
     * @throws HttpException
     * @throws StreamException
     */
    public function getRoadRunnerStreamedJsonResponse(REST $rest): void
    {
        $this->getStreamedJsonResponseTest($rest, '/returnJsonResponse');
    }


    /**
     * @throws ClosedException
     * @throws FilesystemException
     * @throws HttpException
     * @throws StreamException
     */
    public function getOriginalStreamedJsonResponse(REST $rest): void
    {
        $this->getStreamedJsonResponseTest($rest, '/returnOriginalJsonResponse');
    }


    /**
     * @param non-empty-string $uri
     *
     * @throws FilesystemException
     * @throws HttpException
     * @throws StreamException
     * @throws ClosedException
     */
    private function getStreamedJsonResponseTest(REST $rest, string $uri): void
    {
        /** @var non-empty-string $host */
        $host    = $rest->_getConfig('url');
        $client  = HttpClientBuilder::buildDefault();
        $request = new Request($host . $uri);

        $response = $client->request($request);

        $path = codecept_output_dir(sprintf('streamed-%s.json', ByteString::fromRandom(20)->toString()));
        $file = openFile($path, 'w');

        while (null !== $chunk = $response->getBody()->read()) {
            $file->write($chunk);
        }

        $file->close();

        $stream = fopen($path, 'r');

        assertIsResource($stream);

        try {
            $parser = new Parser($stream, new InMemoryListener());
            $parser->parse();

        } catch (ParsingException $e) {
            /**@phpstan-ignore-next-line function.impossibleType */
            assertTrue(false, $e->getMessage());
        } finally {
            fclose($stream);
        }
    }
}
