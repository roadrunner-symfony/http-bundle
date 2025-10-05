<?php


declare(strict_types=1);

use PHPUnit\Framework\Constraint\IsEqual;
use Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedJsonResponse;
use Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedResponse;
use Roadrunner\Integration\Symfony\Http\RoadRunnerHttpBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedJsonResponse as SymfonyStreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Attribute\Route;

require __DIR__ . '/../../vendor/autoload_runtime.php';

/**
 * @noinspection PhpIllegalPsrClassPathInspection
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new RoadRunnerHttpBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'S0ME_SECRET',
        ]);

        $container->parameters()
            ->set('.container.dumper.inline_factories', true)
        ;
    }


    #[Route(path: '/acceptTextRequest', defaults: ['_format' => 'json'], methods: [Request::METHOD_POST], format: 'json')]
    public function acceptTextRequest(Request $request): Response
    {
        /** @var non-empty-string|null $ext */
        $ext = MimeTypes::getDefault()->getExtensions($request->headers->get('CONTENT_TYPE', ''))[0];

        if ($ext == null) {
            return new JsonResponse(['test' => false, 'message' => 'Failed get ext']);
        }

        $expectedContent = @file_get_contents(sprintf(__DIR__ . '/../_data/request.%s', $ext));

        if ($expectedContent === false) {
            return new JsonResponse(['test' => false, 'message' => 'Failed get expected content']);
        }

        $constraint = new IsEqual($expectedContent);
        $test       = $constraint->evaluate((string) $request->getContent(), returnResult: true);

        return new JsonResponse(['test' => $test, 'message' => $test ? 'Passed' : 'Failed equal request content']);
    }


    #[Route(path: '/acceptBinaryRequest', defaults: ['_format' => 'json',], methods: [Request::METHOD_POST], format: 'json')]
    public function acceptBinaryRequest(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if ($file == null) {
            return new JsonResponse(['test' => false, 'message' => 'Failed get file']);
        }

        /** @var non-empty-string|null $ext */
        $ext = $request->headers->get('x-ext', '');

        if ($ext == null) {
            return new JsonResponse(['test' => false, 'message' => 'Failed get ext']);
        }

        $expectedContent = @file_get_contents(sprintf(__DIR__ . '/../_data/request.%s', $ext));

        if ($expectedContent === false) {
            return new JsonResponse(['test' => false, 'message' => 'Failed get expected content']);
        }

        $constraint = new IsEqual($expectedContent);
        $test       = $constraint->evaluate($file->getContent(), returnResult: true);

        return new JsonResponse(['test' => $test, 'message' => $test ? 'Passed' : 'Failed equal file content']);
    }


    #[Route(path: '/returnBinaryResponse', defaults: ['_format' => 'json',], methods: [Request::METHOD_GET], format: 'json')]
    public function returnBinaryResponse(Request $request): Response
    {
        /** @var non-empty-string|null $ext */
        $ext = MimeTypes::getDefault()->getExtensions($request->headers->get('Accept', ''))[0];

        if ($ext == null) {
            return new JsonResponse(['test' => false, 'message' => 'Failed get ext']);
        }

        return new BinaryFileResponse(sprintf(__DIR__ . '/../_data/request.%s', $ext));
    }

    #[Route(path: '/returnTextResponse', methods: [Request::METHOD_GET])]
    public function returnTextResponse(Request $request): Response
    {
        $contentType = $request->headers->get('Accept', '');

        /** @var non-empty-string|null $ext */
        $ext = MimeTypes::getDefault()->getExtensions($contentType)[0];

        if ($ext == null) {
            return new JsonResponse(['test' => false, 'message' => 'Failed get ext']);
        }

        return new Response(file_get_contents(sprintf(__DIR__ . '/../_data/request.%s', $ext)) ?? '', headers: ['Content-Type' => $contentType]);
    }


    #[Route(path: '/returnStreamingResponse', methods: [Request::METHOD_GET])]
    public function returnStreamingResponse(): Response
    {
        return new StreamedResponse(
            (static function (): \Generator {
                for ($i = 0; $i < 1550; $i++) {
                    yield random_int(1, $i + 1) . PHP_EOL;
                }
            })()
        );
    }


    #[Route(path: '/returnOriginalStreamingResponse', methods: [Request::METHOD_GET])]
    public function returnOriginalStreamingResponse(): Response
    {
        return new SymfonyStreamedResponse(
            (static function (): \Generator {
                for ($i = 0; $i < 1550; $i++) {
                    yield random_int(1, $i + 1) . PHP_EOL;
                }
            })()
        );
    }



    #[Route(path: '/returnJsonResponse', methods: [Request::METHOD_GET])]
    public function returnJsonResponse(): StreamedJsonResponse
    {
        return new StreamedJsonResponse(
            (static function (): \Generator {
                for ($i = 0; $i < 1500; $i++) {
                    if ($i % 2 === 0) {
                        yield new class('Vlad Shashkov', 27) {
                            public function __construct(
                                public string $name,
                                public int $age,
                            ) {}
                        };
                    }

                    if ($i % 3 === 0) {
                        yield new class('Spiral', 'RoadRunner') {
                            public function __construct(
                                public string $name,
                                public string $product,
                            ) {}
                        };
                    }


                    yield ['generator' => random_int(1, $i + 1)];
                }
            })()
        );
    }


    #[Route(path: '/returnOriginalJsonResponse', methods: [Request::METHOD_GET])]
    public function returnOriginalJsonResponse(): SymfonyStreamedJsonResponse
    {
        return new SymfonyStreamedJsonResponse(
            (static function (): \Generator {
                for ($i = 0; $i < 1500; $i++) {
                    if ($i % 2 === 0) {
                        yield new class('Vlad Shashkov', 27) {
                            public function __construct(
                                public string $name,
                                public int $age,
                            ) {}
                        };
                    }

                    if ($i % 3 === 0) {
                        yield new class('Spiral', 'RoadRunner') {
                            public function __construct(
                                public string $name,
                                public string $product,
                            ) {}
                        };
                    }


                    yield ['generator' => random_int(1, $i + 1)];
                }
            })()
        );
    }
}

return static function (array $context): Kernel {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
