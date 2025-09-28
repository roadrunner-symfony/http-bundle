<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Runtime;

use JsonException;
use Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedResponse;
use Spiral\RoadRunner\Http\GlobalState;
use Spiral\RoadRunner\Http\HttpWorkerInterface as RoadRunnerHttpWorker;
use Spiral\RoadRunner\Http\Request as RoadRunnerHttpRequest;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface as ErrorRenderer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * @phpstan-import-type UploadedFilesList from RoadRunnerHttpRequest
 */
final class HttpWorker
{
    public function __construct(
        private readonly ErrorRenderer $errorRenderer,
        private readonly RoadRunnerHttpWorker $worker,
    ) {}

    public function waitRequest(): ?SymfonyRequest
    {
        $request = $this->worker->waitRequest();

        if (null == $request) {
            return null;
        }


        try {
            $parsedBody = $request->getParsedBody() ?? [];
        } catch (JsonException) {
            $parsedBody = [];
        }


        $symfonyRequest = new SymfonyRequest(
            $request->query,
            $parsedBody,
            $request->attributes,
            $request->cookies,
            $this->mapUploadedFiles($request->uploads),
            $_SERVER = GlobalState::enrichServerVars($request),
            $request->body
        );

        $symfonyRequest->headers->add($request->headers);

        return $symfonyRequest;
    }


    /**
     * @param UploadedFilesList $files
     *
     * @return array<array-key, UploadedFile| array<array-key, UploadedFile>>
     */
    private function mapUploadedFiles(array $files): array
    {
        $result = [];

        foreach ($files as $index => $file) {
            /** @phpstan-ignore nullCoalesce.offset,equal.alwaysFalse  */
            if (($file['name'] ?? null) == null) {

                /** @phpstan-ignore argument.type */
                $result[$index] = $this->mapUploadedFiles($file);

                continue;
            }

            $result[$index] = new UploadedFile(
                $file['tmpName'],
                $file['name'],
                $file['mime'],
                $file['error'],
                true
            );
        }

        return $result;
    }



    public function respondThrowable(Throwable $exception): SymfonyResponse
    {
        $newException = $this->errorRenderer->render($exception);

        $this->worker->respond(
            $newException->getStatusCode(),
            $newException->getAsString(),
            $newException->getHeaders(),
        );


        return new SymfonyResponse(
            $newException->getAsString(),
            $newException->getStatusCode(),
            $newException->getHeaders(),
        );
    }


    public function respond(SymfonyResponse $response): void
    {
        /**
         * @param array<string, array<int, string|null>>|array<int, string|null> $headers
         *
         * @return array<int|string, string[]>
         */
        $stringifyHeaders = static function (array $headers): array {
            return array_map(static function ($headerValues): array {
                return array_map(static fn($val): string => (string) $val, (array) $headerValues);
            }, $headers);
        };

        if ($response instanceof BinaryFileResponse) {
            $this->worker->respond(
                $response->getStatusCode(),
                $response->getFile()->getContent(),
                $stringifyHeaders($response->headers->all())
            );

            return;
        }

        if ($response instanceof StreamedResponse) {
            $this->worker->respond(
                $response->getStatusCode(),
                $response->lazyContent,
                $stringifyHeaders($response->headers->all())
            );

            return;
        }

        $content = $response->getContent();

        if (!$content) {
            $content = '';
        }

        $this->worker->respond(
            $response->getStatusCode(),
            $content,
            $stringifyHeaders($response->headers->all())
        );
    }
}
