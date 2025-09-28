<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\Acceptance;

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
    private function sendTextRequestDataProvider(): iterable
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
    private function sendBinaryRequestDataProvider(): iterable
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
    private function getBinaryResponseDataProvider(): iterable
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
    private function getTextResponseDataProvider(): iterable
    {
        yield ['file' => 'request.json', 'content-type' => 'application/json'];
        yield ['file' => 'request.xml', 'content-type' => 'application/xml'];
        yield ['file' => 'request.yaml', 'content-type' => 'application/yaml'];
    }


    /**
     * @throws HttpException
     * @throws StreamException
     */
    public function getStreamedResponse(REST $rest): void
    {
        $client   = HttpClientBuilder::buildDefault();
        $request  = new Request($rest->_getConfig('url') . '/returnStreamingResponse');
        $response = $client->request($request);

        $count = 0;

        while (null !== $chunk = $response->getBody()->read()) {
            $count++;
        }

        assertEquals(1502, $count);
    }


    /**
     * @throws FilesystemException
     * @throws HttpException
     */
    public function getStreamedJsonResponse(REST $rest, AcceptanceTester $tester): void
    {
        $client   = HttpClientBuilder::buildDefault();
        $request  = new Request($rest->_getConfig('url') . '/returnJsonResponse');
        $response = $client->request($request);

        $path = codecept_output_dir(sprintf('streamed-%s.json', ByteString::fromRandom(20)->toString()));
        $file = openFile($path, 'w');

        $bytes = 0;

        while (null !== $chunk = $response->getBody()->read()) {
            $file->write($chunk);
        }

        $stream = fopen($path, 'r');

        try {
            $parser = new Parser($stream, new InMemoryListener());
            $parser->parse();

        } catch (ParsingException $e) {
            assertTrue(false, $e->getMessage());
        } finally {
            fclose($stream);
            $file->close();
        }
    }
}
