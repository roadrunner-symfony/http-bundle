<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\Encoder\Json;

use Closure;
use Generator;
use Iterator;
use JsonSerializable;
use RuntimeException;
use Traversable;

/**
 * @ref https://packagist.org/packages/violet/streaming-json-encoder
 *
 * @todo migrate to shashkov/stream
 */
final class JsonStream implements Iterator
{
    /** @var Iterator[] Current value stack in encoding */
    private array $stack;

    /** @var bool[] True for every object in the stack, false for an array */
    private array $stackType;

    /** @var array Stack of values being encoded */
    private array $valueStack;

    /** Whether the next value is the first value in an array or an object */
    private bool $first;


    /** @var bool Whether next token should be preceded by new line or not */
    private bool $newLine;

    /** @var string Indent to use for indenting JSON output */
    private string $indent;

    /** @var string[] Errors that occurred in encoding */
    private array $errors;

    /** @var int Number of the current line in output */
    private int $line;

    /** @var int Number of the current column in output */
    private int $column;


    /** @var int|null The current step of the encoder */
    private ?int $step;


    /** @var string The encoded JSON in the current step */
    private string $buffer;


    public function __construct(
        public iterable|object $stream,
        public int $encodeOptions = 0,
    ) {
        $this->errors     = [];
        $this->stack      = [];
        $this->stackType  = [];
        $this->valueStack = [];
        $this->newLine    = false;
        $this->first      = true;
        $this->step       = null;
        $this->buffer     = '';
        $this->indent     = '    ';
    }

    /**
     * Sets the JSON encoding options.
     *
     * @throws RuntimeException If changing encoding options during encoding operation
     */
    public function setEncodeOptions(int $encodeOptions)
    {
        if ($this->step !== null) {
            throw new RuntimeException('Cannot change encoding options during encoding');
        }

        $this->encodeOptions = $encodeOptions;

        return $this;
    }

    /**
     * Sets the indent for the JSON output.
     *
     * @param string|int $indent A string to use as indent or the number of spaces
     *
     * @throws RuntimeException If changing indent during encoding operation
     */
    public function setIndent(string|int $indent): self
    {
        if ($this->step !== null) {
            throw new RuntimeException('Cannot change indent during encoding');
        }

        $this->indent = is_int($indent) ? str_repeat(' ', $indent) : $indent;
        return $this;
    }

    /**
     * Returns the list of errors that occurred during the last encoding process.
     *
     * @return string[] List of errors that occurred during encoding
     */
    public function getErrors(): array
    {
        return $this->errors;
    }


    /**
     * Initializes the iterator if it has not been initialized yet.
     */
    private function initialize(): void
    {
        if (!isset($this->stack)) {
            $this->rewind();
        }
    }

    /**
     * Returns the current number of step in the encoder.
     *
     * @return int|null The current step number as integer or null if the current state is not valid
     */
    public function key(): ?int
    {
        $this->initialize();

        return $this->step;
    }

    /**
     * Tells if the encoder has a valid current state.
     *
     * @return bool True if the iterator has a valid state, false if not
     */
    public function valid(): bool
    {
        $this->initialize();

        return $this->step !== null;
    }

    public function current(): ?string
    {
        return $this->valid() ? $this->buffer : null;
    }

    /**
     * Returns the JSON encoding to the beginning.
     */
    public function rewind(): void
    {
        $this->buffer = '';

        if ($this->step === 0) {
            return;
        }

        $this->stack      = [];
        $this->stackType  = [];
        $this->valueStack = [];
        $this->errors     = [];
        $this->newLine    = false;
        $this->first      = true;
        $this->line       = 1;
        $this->column     = 1;
        $this->step       = 0;

        $this->processValue($this->stream);
    }

    /**
     * Iterates the next token or tokens to the output stream.
     */
    public function next(): void
    {
        $this->initialize();

        $this->buffer = '';

        if ($this->stack != []) {
            $this->step++;
            $iterator = end($this->stack);

            if ($iterator->valid()) {
                $this->processStack($iterator, end($this->stackType));
                $iterator->next();


                return;
            }

            $this->popStack();

            return;
        }


        $this->step = null;
    }

    /**
     * Handles the next value from the iterator to be encoded as JSON.
     *
     * @param Iterator $iterator The iterator used to generate the next value
     * @param bool      $isObject True if the iterator is being handled as an object, false if not
     */
    private function processStack(Iterator $iterator, bool $isObject): void
    {
        if ($isObject) {
            if (!$this->processKey($iterator->key())) {
                return;
            }
        } elseif (!$this->first) {
            $this->outputLine(',');
        }

        $this->first = false;
        $this->processValue($iterator->current());
    }

    /**
     * Handles the given value key into JSON.
     *
     * @param mixed $key The key to process
     *
     * @return bool True if the key is valid, false if not
     */
    private function processKey(mixed $key): bool
    {
        if (!is_int($key) && !is_string($key)) {
            $this->addError('Only string or integer keys are supported');
            return false;
        }

        if (!$this->first) {
            $this->outputLine(',');
        }

        $this->outputJson((string) $key);
        $this->output(':');

        if ($this->encodeOptions & JSON_PRETTY_PRINT) {
            $this->output(' ');
        }

        return true;
    }

    /**
     * Handles the given JSON value appropriately depending on it's type.
     *
     * @param mixed $value The value that should be encoded as JSON
     */
    private function processValue(mixed $value): void
    {
        $this->valueStack[] = $value;
        $value              = $this->resolveValue($value);


        if (is_array($value) || is_object($value)) {
            $this->pushStack($value);

            return;
        }

        $this->outputJson($value);
        array_pop($this->valueStack);
    }

    /**
     * Resolves the actual value of any given value that is about to be processed.
     *
     * @param mixed $value The value to resolve
     *
     * @return mixed The resolved value
     */
    protected function resolveValue(mixed $value): mixed
    {
        do {
            if ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            } elseif ($value instanceof Closure) {
                $value = $value();
            } else {
                break;
            }
        } while (true);

        return $value;
    }

    /**
     * Adds an JSON encoding error to the list of errors.
     *
     * @param string $message The error message to add
     *
     * @throws RuntimeException If the encoding should not continue due to the error
     */
    private function addError(string $message): void
    {
        $errorMessage   = sprintf('Line %d, column %d: %s', $this->line, $this->column, $message);
        $this->errors[] = $errorMessage;

        if ($this->encodeOptions & JSON_PARTIAL_OUTPUT_ON_ERROR) {
            return;
        }

        $this->stack = [];
        $this->step  = null;

        throw new RuntimeException($errorMessage);
    }

    /**
     * Pushes the given iterable to the value stack.
     */
    private function pushStack(object|iterable $iterable): void
    {
        $newIterator = static function (iterable|object $value): Generator {
            foreach ($value as $k => $v) {
                yield $k => $v;
            }
        };

        $iterator = $newIterator($iterable);
        $isObject = $this->isObject($iterable, $iterator);

        if ($isObject) {
            $this->outputLine('{');
        }

        if (!$isObject) {
            $this->outputLine('[');
        }

        $this->first       = true;
        $this->stack[]     = $iterator;
        $this->stackType[] = $isObject;
    }

    /**
     * Tells if the given iterable should be handled as a JSON object or not.
     *
     * @param object|array $iterable The iterable value to test
     * @param Iterator    $iterator An Iterator created from the iterable value
     *
     * @return bool True if the given iterable should be treated as object, false if not
     */
    private function isObject($iterable, Iterator $iterator)
    {
        if ($this->encodeOptions & JSON_FORCE_OBJECT) {
            return true;
        }

        if ($iterable instanceof Traversable) {
            return $iterator->valid() && $iterator->key() !== 0;
        }

        return is_object($iterable) || $this->isAssociative($iterable);
    }

    /**
     * Tells if the given array is an associative array.
     *
     * @param array $array The array to test
     *
     * @return bool True if the array is associative, false if not
     */
    private function isAssociative(array $array)
    {
        if ($array === []) {
            return false;
        }

        $expected = 0;

        foreach ($array as $key => $_) {
            if ($key !== $expected++) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes the top element of the value stack.
     */
    private function popStack(): void
    {
        if (!$this->first) {
            $this->newLine = true;
        }

        $this->first = false;
        array_pop($this->stack);

        if (array_pop($this->stackType)) {
            $this->output('}');
        } else {
            $this->output(']');
        }

        array_pop($this->valueStack);
    }

    /**
     * Encodes the given value as JSON and passes it to output stream.
     *
     * @param mixed $value The value to output as JSON
     */
    private function outputJson(mixed $value): void
    {
        $encoded = json_encode($value, $this->encodeOptions);
        $error   = json_last_error();

        if ($error !== JSON_ERROR_NONE) {
            $this->addError(sprintf('%s (%s)', json_last_error_msg(), $this->getJsonErrorName($error)));
        }

        $this->output($encoded);
    }

    /**
     * Returns the name of the JSON error constant.
     *
     * @param int $error The error code to find
     *
     * @return string The name for the error code
     */
    private function getJsonErrorName(int $error): string
    {
        $matches      = array_keys(get_defined_constants(), $error, true);
        $prefix       = 'JSON_ERROR_';
        $prefixLength = strlen($prefix);
        $name         = 'UNKNOWN_ERROR';

        foreach ($matches as $match) {
            if (is_string($match) && strncmp($match, $prefix, $prefixLength) === 0) {
                $name = $match;
                break;
            }
        }

        return $name;
    }

    /**
     * Passes the given token to the output stream and ensures the next token is preceded by a newline.
     *
     * @param string $string The token to write to the output stream
     */
    private function outputLine(string $string): void
    {
        $this->output($string);
        $this->newLine = true;
    }

    /**
     * Passes the given token to the output stream.
     *
     * @param string $string The token to write to the output stream
     */
    private function output($string): void
    {
        if ($this->newLine && $this->encodeOptions & JSON_PRETTY_PRINT) {
            $indent = str_repeat($this->indent, count($this->stack));
            $this->write("\n");

            if ($indent !== '') {
                $this->write($indent);
            }

            $this->line++;
            $this->column = strlen($indent) + 1;
        }

        $this->newLine = false;
        $this->write($string);
        $this->column += strlen($string);
    }

    /**
     * Actually handles the writing of the given token to the output stream.
     *
     * @param string $string The given token to write
     *
     */
    private function write(string $string): void
    {
        $this->buffer .= $string;
    }
}
