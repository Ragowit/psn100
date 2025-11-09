<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Json;

final class Decoder
{
    private string $input = '';

    private int $length = 0;

    private int $position = 0;

    /**
     * @return mixed
     * @throws DecodingException
     */
    public function decode(string $json)
    {
        $this->input = $json;
        $this->length = strlen($json);
        $this->position = 0;

        $this->skipWhitespace();
        $value = $this->parseValue();
        $this->skipWhitespace();

        if ($this->position !== $this->length) {
            throw new DecodingException('Unexpected trailing characters in JSON payload.');
        }

        return $value;
    }

    /**
     * @return mixed
     * @throws DecodingException
     */
    private function parseValue()
    {
        $char = $this->peek();

        if ($char === null) {
            throw new DecodingException('Unexpected end of JSON payload.');
        }

        return match ($char) {
            '{' => $this->parseObject(),
            '[' => $this->parseArray(),
            '"' => $this->parseString(),
            't' => $this->consumeLiteral('true', true),
            'f' => $this->consumeLiteral('false', false),
            'n' => $this->consumeLiteral('null', null),
            '-', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' => $this->parseNumber(),
            default => throw new DecodingException(sprintf('Unexpected character "%s" in JSON payload.', $char)),
        };
    }

    /**
     * @return array<mixed>
     * @throws DecodingException
     */
    private function parseArray(): array
    {
        $this->position++;
        $this->skipWhitespace();

        $result = [];

        if ($this->peek() === ']') {
            $this->position++;
            return $result;
        }

        while (true) {
            $this->skipWhitespace();
            $result[] = $this->parseValue();
            $this->skipWhitespace();

            $char = $this->peek();

            if ($char === ',') {
                $this->position++;
                continue;
            }

            if ($char === ']') {
                $this->position++;
                break;
            }

            throw new DecodingException('Unterminated array in JSON payload.');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     * @throws DecodingException
     */
    private function parseObject(): array
    {
        $this->position++;
        $this->skipWhitespace();

        $result = [];

        if ($this->peek() === '}') {
            $this->position++;
            return $result;
        }

        while (true) {
            $this->skipWhitespace();
            if ($this->peek() !== '"') {
                throw new DecodingException('Object keys must be strings.');
            }

            $key = $this->parseString();
            $this->skipWhitespace();

            if ($this->peek() !== ':') {
                throw new DecodingException('Expected colon after object key.');
            }

            $this->position++;
            $this->skipWhitespace();
            $value = $this->parseValue();
            $result[$key] = $value;
            $this->skipWhitespace();

            $char = $this->peek();

            if ($char === ',') {
                $this->position++;
                continue;
            }

            if ($char === '}') {
                $this->position++;
                break;
            }

            throw new DecodingException('Unterminated object in JSON payload.');
        }

        return $result;
    }

    /**
     * @throws DecodingException
     */
    private function parseString(): string
    {
        $this->position++;
        $result = '';

        while (true) {
            if ($this->position >= $this->length) {
                throw new DecodingException('Unterminated string in JSON payload.');
            }

            $char = $this->input[$this->position];

            if ($char === '"') {
                $this->position++;
                break;
            }

            if ($char === '\\') {
                $this->position++;
                if ($this->position >= $this->length) {
                    throw new DecodingException('Invalid escape sequence in JSON string.');
                }

                $escaped = $this->input[$this->position];
                $this->position++;
                $result .= $this->decodeEscapedCharacter($escaped);
                continue;
            }

            $result .= $char;
            $this->position++;
        }

        return $result;
    }

    /**
     * @throws DecodingException
     */
    private function decodeEscapedCharacter(string $escaped): string
    {
        return match ($escaped) {
            '"', '\\', '/' => $escaped,
            'b' => "\x08",
            'f' => "\x0c",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'u' => $this->decodeUnicodeEscape(),
            default => throw new DecodingException(sprintf('Invalid escape character "\\%s" in JSON string.', $escaped)),
        };
    }

    /**
     * @throws DecodingException
     */
    private function decodeUnicodeEscape(): string
    {
        $codepoint = $this->consumeHexadecimalSequence();

        if ($codepoint >= 0xD800 && $codepoint <= 0xDBFF) {
            if (!$this->startsWith('\\u', $this->position)) {
                throw new DecodingException('Invalid Unicode surrogate pair in JSON string.');
            }

            $this->position += 2;
            $lowSurrogate = $this->consumeHexadecimalSequence();

            if ($lowSurrogate < 0xDC00 || $lowSurrogate > 0xDFFF) {
                throw new DecodingException('Invalid Unicode surrogate pair in JSON string.');
            }

            $codepoint = 0x10000 + (($codepoint - 0xD800) << 10) + ($lowSurrogate - 0xDC00);
        }

        return $this->codepointToUtf8($codepoint);
    }

    /**
     * @throws DecodingException
     */
    private function consumeHexadecimalSequence(): int
    {
        if ($this->position + 4 > $this->length) {
            throw new DecodingException('Incomplete Unicode escape sequence.');
        }

        $sequence = substr($this->input, $this->position, 4);

        if (!ctype_xdigit($sequence)) {
            throw new DecodingException('Invalid Unicode escape sequence.');
        }

        $this->position += 4;

        return hexdec($sequence);
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }

        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3F));
        }

        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F));
    }

    /**
     * @throws DecodingException
     */
    private function parseNumber(): int|float|string
    {
        $start = $this->position;
        $char = $this->peek();

        if ($char === '-') {
            $this->position++;
        }

        $char = $this->peek();

        if ($char === null) {
            throw new DecodingException('Invalid number in JSON payload.');
        }

        if ($char === '0') {
            $this->position++;
        } else {
            if (!$this->isDigit($char)) {
                throw new DecodingException('Invalid number in JSON payload.');
            }

            while (($char = $this->peek()) !== null && $this->isDigit($char)) {
                $this->position++;
            }
        }

        $isFloat = false;
        $char = $this->peek();

        if ($char === '.') {
            $isFloat = true;
            $this->position++;
            $char = $this->peek();

            if ($char === null || !$this->isDigit($char)) {
                throw new DecodingException('Invalid number in JSON payload.');
            }

            while (($char = $this->peek()) !== null && $this->isDigit($char)) {
                $this->position++;
            }
        }

        $char = $this->peek();

        if ($char === 'e' || $char === 'E') {
            $isFloat = true;
            $this->position++;
            $char = $this->peek();

            if ($char === '+' || $char === '-') {
                $this->position++;
                $char = $this->peek();
            }

            if ($char === null || !$this->isDigit($char)) {
                throw new DecodingException('Invalid number in JSON payload.');
            }

            while (($char = $this->peek()) !== null && $this->isDigit($char)) {
                $this->position++;
            }
        }

        $numberString = substr($this->input, $start, $this->position - $start);

        if (!$isFloat && $this->isSafeInteger($numberString)) {
            return (int) $numberString;
        }

        if (!$isFloat) {
            return $numberString;
        }

        return (float) $numberString;
    }

    /**
     * @template TValue
     * @param TValue $value
     * @return TValue
     * @throws DecodingException
     */
    private function consumeLiteral(string $literal, $value)
    {
        if (!$this->startsWith($literal, $this->position)) {
            throw new DecodingException(sprintf('Invalid literal "%s" in JSON payload.', $literal));
        }

        $this->position += strlen($literal);

        return $value;
    }

    private function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function skipWhitespace(): void
    {
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            if ($char !== ' ' && $char !== "\n" && $char !== "\r" && $char !== "\t") {
                break;
            }

            $this->position++;
        }
    }

    private function peek(): ?string
    {
        if ($this->position >= $this->length) {
            return null;
        }

        return $this->input[$this->position];
    }

    private function startsWith(string $needle, int $offset): bool
    {
        return $offset + strlen($needle) <= $this->length && substr($this->input, $offset, strlen($needle)) === $needle;
    }

    private function isSafeInteger(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $isNegative = false;
        $offset = 0;

        $first = $value[0];
        if ($first === '+' || $first === '-') {
            $isNegative = $first === '-';
            $offset = 1;
        }

        $digits = substr($value, $offset);

        if ($digits === '' || strspn($digits, '0123456789') !== strlen($digits)) {
            return false;
        }

        if (strlen($digits) < 19) {
            return true;
        }

        if (strlen($digits) > 19) {
            return false;
        }

        if ($isNegative) {
            $limit = substr((string) PHP_INT_MIN, 1);
            return strcmp($digits, $limit) <= 0;
        }

        $limit = (string) PHP_INT_MAX;
        return strcmp($digits, $limit) <= 0;
    }
}
