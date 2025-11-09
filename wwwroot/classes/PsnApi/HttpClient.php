<?php

declare(strict_types=1);

namespace Achievements\PsnApi;

use Achievements\PsnApi\Exceptions\ApiException;
use Achievements\PsnApi\Exceptions\AuthenticationException;

final class HttpClient
{
    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new ApiException('Unable to initialize HTTP request.', 0);
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $formattedHeaders);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_ENCODING, $this->getAcceptedEncodings());

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($handle);

        if ($response === false) {
            $errorMessage = curl_error($handle);
            curl_close($handle);
            throw new ApiException($errorMessage !== '' ? $errorMessage : 'HTTP request failed.', 0);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        $headerPart = substr($response, 0, $headerSize);
        $headersMap = $this->parseResponseHeaders($headerPart);
        $bodyPart = substr($response, $headerSize);

        if ($bodyPart !== '' && isset($headersMap['content-encoding'])) {
            $bodyPart = $this->decodeBody($bodyPart, $headersMap['content-encoding']);
        }

        if ($bodyPart !== '') {
            $bodyPart = $this->stripUtf8Bom($bodyPart);
        }

        if ($statusCode === 401) {
            throw new AuthenticationException('Authentication failed with the PlayStation Network API.', $statusCode);
        }

        $decodedBody = null;
        $trimmedBody = trim($bodyPart);
        if ($trimmedBody !== '') {
            try {
                /** @var array<string, mixed>|null $decodedBody */
                $decodedBody = json_decode($trimmedBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new ApiException('Unable to decode response from the PlayStation Network API.', $statusCode, null, $exception);
            }
        }

        if ($statusCode >= 400) {
            throw new ApiException('Unexpected response from the PlayStation Network API.', $statusCode, $decodedBody);
        }

        return $decodedBody ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function requestHeaders(string $method, string $url, array $headers = []): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new ApiException('Unable to initialize HTTP request.', 0);
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $formattedHeaders);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_ENCODING, $this->getAcceptedEncodings());
        curl_setopt($handle, CURLOPT_NOBODY, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

        $response = curl_exec($handle);

        if ($response === false) {
            $errorMessage = curl_error($handle);
            curl_close($handle);
            throw new ApiException($errorMessage !== '' ? $errorMessage : 'HTTP request failed.', 0);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($statusCode >= 400) {
            throw new ApiException('Unexpected response from the PlayStation Network API.', $statusCode);
        }

        return $this->parseResponseHeaders((string) $response);
    }

    /**
     * @return array<string, string>
     */
    private function parseResponseHeaders(string $rawHeaders): array
    {
        $trimmed = trim($rawHeaders);

        if ($trimmed === '') {
            return [];
        }

        $sections = explode("\r\n\r\n", $trimmed);
        $lastSection = array_pop($sections);

        if ($lastSection === null || $lastSection === '') {
            return [];
        }

        $headersMap = [];
        $lines = explode("\r\n", $lastSection);

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $headersMap[strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        return $headersMap;
    }

    private function decodeBody(string $body, string $encodingHeader): string
    {
        $encodings = array_filter(array_map('trim', explode(',', strtolower($encodingHeader))));

        if ($encodings === []) {
            return $body;
        }

        foreach (array_reverse($encodings) as $encoding) {
            $encoding = trim(explode(';', $encoding, 2)[0]);

            switch ($encoding) {
                case 'gzip':
                case 'x-gzip':
                    $decoded = @gzdecode($body);
                    if ($decoded !== false) {
                        $body = $decoded;
                    }
                    break;
                case 'deflate':
                case 'x-deflate':
                    $decoded = @gzuncompress($body);
                    if ($decoded === false) {
                        $decoded = @gzinflate($body);
                    }
                    if ($decoded !== false) {
                        $body = $decoded;
                    }
                    break;
                case 'br':
                    if (function_exists('brotli_uncompress')) {
                        $decoded = @brotli_uncompress($body);
                        if ($decoded !== false) {
                            $body = $decoded;
                        }
                    }
                    break;
                case 'zstd':
                    if (function_exists('zstd_uncompress')) {
                        $decoded = @zstd_uncompress($body);
                        if ($decoded !== false) {
                            $body = $decoded;
                        }
                    }
                    break;
                case 'identity':
                case 'none':
                    break;
            }
        }

        return $body;
    }

    private function stripUtf8Bom(string $body): string
    {
        if (substr($body, 0, 3) === "\xEF\xBB\xBF") {
            return substr($body, 3);
        }

        return $body;
    }

    private function getAcceptedEncodings(): string
    {
        $encodings = ['gzip', 'deflate'];

        if (function_exists('zstd_uncompress')) {
            $encodings[] = 'zstd';
        }

        if (function_exists('brotli_uncompress')) {
            $encodings[] = 'br';
        }

        $encodings[] = 'identity';

        return implode(', ', $encodings);
    }
}

