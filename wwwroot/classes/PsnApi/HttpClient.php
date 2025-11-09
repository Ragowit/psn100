<?php

declare(strict_types=1);

namespace Achievements\PsnApi;

use Achievements\PsnApi\Exceptions\ApiException;
use Achievements\PsnApi\Exceptions\AuthenticationException;
use Achievements\PsnApi\Json\Decoder;
use Achievements\PsnApi\Json\DecodingException;

final class HttpClient
{
    private Decoder $decoder;

    public function __construct(?Decoder $decoder = null)
    {
        $this->decoder = $decoder ?? new Decoder();
    }

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
        curl_setopt($handle, CURLOPT_ENCODING, '');

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
        $bodyPart = substr($response, $headerSize);
        $contentEncoding = $this->extractHeaderValue($headerPart, 'content-encoding');

        if ($statusCode === 401) {
            throw new AuthenticationException('Authentication failed with the PlayStation Network API.', $statusCode);
        }

        $decodedBody = null;
        $trimmedBody = trim($bodyPart);
        if ($trimmedBody !== '') {
            try {
                $decodedBody = $this->decodeResponse($trimmedBody);
            } catch (DecodingException $exception) {
                $fallbackBody = $contentEncoding !== null
                    ? $this->attemptDecompression($bodyPart, $contentEncoding)
                    : $bodyPart;

                if ($fallbackBody !== $bodyPart) {
                    try {
                        $decodedBody = $this->decodeResponse(trim($fallbackBody));
                    } catch (DecodingException $innerException) {
                        throw new ApiException(
                            'Unable to decode response from the PlayStation Network API.',
                            $statusCode,
                            null,
                            $innerException
                        );
                    }
                } else {
                    throw new ApiException('Unable to decode response from the PlayStation Network API.', $statusCode, null, $exception);
                }
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
        curl_setopt($handle, CURLOPT_ENCODING, '');
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

        $headersMap = [];
        $lines = preg_split('/\r\n/', trim((string) $response));
        if ($lines === false) {
            return $headersMap;
        }

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $headersMap[strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        return $headersMap;
    }

    private function extractHeaderValue(string $headerBlob, string $headerName): ?string
    {
        $segments = preg_split('/\r\n\r\n/', $headerBlob);

        if ($segments === false || $segments === []) {
            return null;
        }

        $lastSegment = trim((string) array_pop($segments));

        if ($lastSegment === '') {
            return null;
        }

        $headerLines = preg_split('/\r\n/', $lastSegment);

        if ($headerLines === false) {
            return null;
        }

        $normalizedName = strtolower($headerName);

        foreach ($headerLines as $line) {
            $parts = explode(':', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            if (strtolower(trim($parts[0])) === $normalizedName) {
                return trim($parts[1]);
            }
        }

        return null;
    }

    private function attemptDecompression(string $body, string $contentEncoding): string
    {
        $encoding = strtolower($contentEncoding);

        if (str_contains($encoding, 'gzip')) {
            $decoded = @gzdecode($body);

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        if (str_contains($encoding, 'deflate')) {
            $decoded = @gzuncompress($body);

            if (is_string($decoded)) {
                return $decoded;
            }

            $decoded = @gzinflate($body);

            if (is_string($decoded)) {
                return $decoded;
            }

            $decoded = @zlib_decode($body);

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        if (str_contains($encoding, 'br') && function_exists('brotli_uncompress')) {
            $decoded = @brotli_uncompress($body);

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return $body;
    }

    /**
     * @return array<mixed>
     * @throws DecodingException
     */
    private function decodeResponse(string $payload): array
    {
        $decoded = $this->decoder->decode($payload);

        if ($decoded === null) {
            return [];
        }

        if (!is_array($decoded)) {
            return ['value' => $decoded];
        }

        return $decoded;
    }
}
