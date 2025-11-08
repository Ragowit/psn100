<?php

declare(strict_types=1);

namespace PsnApi;

final class HttpClient
{
    private ?string $baseUri;

    /**
     * @var array<string, string>
     */
    private array $defaultHeaders;

    public function __construct(?string $baseUri = null, array $defaultHeaders = [])
    {
        $this->baseUri = $baseUri;
        $this->defaultHeaders = $defaultHeaders;
    }

    public function setHeader(string $name, string $value): void
    {
        $this->defaultHeaders[$name] = $value;
    }

    public function removeHeader(string $name): void
    {
        unset($this->defaultHeaders[$name]);
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     */
    public function get(string $uri, array $query = [], array $headers = []): HttpResponse
    {
        return $this->request('GET', $uri, $query, $headers);
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     * @param array<string, scalar>|null $formParams
     */
    public function post(
        string $uri,
        array $query = [],
        array $headers = [],
        ?array $formParams = null,
        ?string $json = null
    ): HttpResponse {
        return $this->request('POST', $uri, $query, $headers, $formParams, $json);
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     * @param array<string, scalar>|null $formParams
     */
    private function request(
        string $method,
        string $uri,
        array $query = [],
        array $headers = [],
        ?array $formParams = null,
        ?string $json = null
    ): HttpResponse {
        $url = $this->buildUrl($uri, $query);

        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Failed to initialize cURL.');
        }

        $requestHeaders = $this->buildHeaders($headers, $json !== null, $formParams !== null);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeaders);

        if ($json !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        } elseif ($formParams !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($formParams, '', '&', PHP_QUERY_RFC3986));
        }

        $rawResponse = curl_exec($curl);
        if ($rawResponse === false) {
            $error = curl_error($curl);
            curl_close($curl);

            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        $headerPart = substr($rawResponse, 0, $headerSize);
        $bodyPart = substr($rawResponse, $headerSize);
        $body = $bodyPart === false ? '' : $bodyPart;

        if ($statusCode >= 400) {
            $message = $this->buildErrorMessage($method, $url, $statusCode, $body);

            throw new HttpException($method, $url, $statusCode, $body, $message);
        }

        return new HttpResponse($statusCode, $this->parseHeaders($headerPart), $body);
    }

    /**
     * @param array<string, scalar> $query
     */
    private function buildUrl(string $uri, array $query): string
    {
        if ($this->isAbsoluteUri($uri)) {
            $base = $uri;
        } else {
            $base = rtrim((string) $this->baseUri, '/') . '/' . ltrim($uri, '/');
        }

        if ($query === []) {
            return $base;
        }

        return $base . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function isAbsoluteUri(string $uri): bool
    {
        return str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://');
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function buildHeaders(array $headers, bool $isJson, bool $isForm): array
    {
        $merged = $this->defaultHeaders;

        foreach ($headers as $name => $value) {
            $merged[$name] = $value;
        }

        if ($isJson) {
            $merged['Content-Type'] = $merged['Content-Type'] ?? 'application/json';
            $merged['Accept'] = $merged['Accept'] ?? 'application/json';
        } elseif ($isForm) {
            $merged['Content-Type'] = $merged['Content-Type'] ?? 'application/x-www-form-urlencoded';
        }

        $built = [];
        foreach ($merged as $name => $value) {
            $built[] = $name . ': ' . $value;
        }

        return $built;
    }

    private function buildErrorMessage(string $method, string $url, int $statusCode, string $body): string
    {
        $summaryUrl = $this->summarizeUrl($url);
        $details = $this->summarizeErrorBody($body);

        $message = sprintf(
            'HTTP %s %s returned status code %d',
            strtoupper($method),
            $summaryUrl,
            $statusCode
        );

        if ($details !== '') {
            $message .= ': ' . $details;
        } else {
            $message .= '.';
        }

        return $message;
    }

    private function summarizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $path = ($parts['scheme'] ?? '') !== '' && ($parts['host'] ?? '') !== ''
            ? sprintf('%s://%s%s', $parts['scheme'], $parts['host'], $parts['path'] ?? '')
            : ($parts['path'] ?? $url);

        if (!isset($parts['query']) || $parts['query'] === '') {
            return $path;
        }

        parse_str($parts['query'], $query);

        if ($query === []) {
            return $path;
        }

        $keys = array_keys($query);

        return $path . '?[' . implode(', ', $keys) . ']';
    }

    private function summarizeErrorBody(string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            $fields = [];

            foreach (['error', 'error_code', 'errorCode', 'code'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    $fields[] = (string) $decoded[$key];
                    break;
                }
            }

            foreach (['error_description', 'errorMessage', 'message'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    $fields[] = (string) $decoded[$key];
                    break;
                }
            }

            if ($fields !== []) {
                return implode(' - ', $fields);
            }
        }

        return substr($trimmed, 0, 200);
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseHeaders(string $headerChunk): array
    {
        $headers = [];

        $lines = preg_split('/\r?\n/', trim($headerChunk));
        if ($lines === false) {
            return $headers;
        }

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name = trim($name);
                $value = trim($value);

                $headers[$name] ??= [];
                $headers[$name][] = $value;
            }
        }

        return $headers;
    }
}
