<?php

class TestController
{
    public function getInitialRequestSpec(): array
    {
        return [
            'url' => '',
            'method' => 'GET',
            'headersRaw' => '',
            'headers' => [],
            'body' => '',
            'settings' => [
                'jsonPayload' => false,
                'jsonResponse' => false,
                'retryOnNull' => false,
                'retryOnIncompleteHTML' => false,
                'xpath' => [],
            ],
            'history' => [
                'enabled' => true,
                'name' => null,
            ],
            'hasCookies' => false,
        ];
    }

    public function handle(array $post): array
    {
        $action = $post['action'] ?? null;

        if ($action === 'import_curl') {
            $curlInput = isset($post['curl_input']) ? (string)$post['curl_input'] : '';
            $requestSpec = CurlImporter::fromCurl($curlInput);

            return [
                'requestSpec' => $requestSpec,
                'result' => null,
                'phpCode' => null,
                'includeCookies' => $requestSpec['hasCookies'],
            ];
        }

        $requestSpec = $this->buildRequestSpec($post);
        $includeCookies = isset($post['include_cookies']);
        $finalRequestSpec = $this->applyCookiePreference($requestSpec, $includeCookies);

        $result = $this->execute($finalRequestSpec);
        $phpCode = PhpCodeGenerator::fromRequestSpec($finalRequestSpec);

        return [
            'requestSpec' => $requestSpec,
            'result' => $result,
            'phpCode' => $phpCode,
            'includeCookies' => $includeCookies,
        ];
    }

    private function buildRequestSpec(array $post): array
    {
        $headersRaw = isset($post['headers']) ? (string)$post['headers'] : '';
        $xpathInput = isset($post['xpath']) ? (string)$post['xpath'] : '';
        $historyName = isset($post['history_name']) ? trim((string)$post['history_name']) : '';

        $settings = [
            'jsonPayload' => isset($post['jsonPayload']),
            'jsonResponse' => isset($post['jsonResponse']),
            'retryOnNull' => isset($post['retryOnNull']),
            'retryOnIncompleteHTML' => isset($post['retryOnIncompleteHTML']),
            'xpath' => $this->normalizeXpath($xpathInput),
        ];

        $headers = $this->parseHeaders($headersRaw);

        return [
            'url' => isset($post['url']) ? (string)$post['url'] : '',
            'method' => isset($post['method']) ? strtoupper((string)$post['method']) : 'GET',
            'headersRaw' => $headersRaw,
            'headers' => $headers,
            'body' => isset($post['body']) ? (string)$post['body'] : '',
            'settings' => $settings,
            'history' => [
                'enabled' => isset($post['history_enabled']),
                'name' => $historyName !== '' ? $historyName : null,
            ],
            'hasCookies' => $this->containsCookieHeader($headersRaw),
        ];
    }

    private function execute(array $requestSpec): array
    {
        $settings = [
            'method' => $requestSpec['method'],
            'jsonPayload' => (bool)$requestSpec['settings']['jsonPayload'],
            'jsonResponse' => (bool)$requestSpec['settings']['jsonResponse'],
            'retryOnNull' => (bool)$requestSpec['settings']['retryOnNull'],
            'retryOnIncompleteHTML' => (bool)$requestSpec['settings']['retryOnIncompleteHTML'],
            'xpath' => $requestSpec['settings']['xpath'],
        ];

        if (in_array($requestSpec['method'], ['POST', 'PUT', 'PATCH'], true)) {
            $settings['postHeaders'] = $requestSpec['headers'];
        } else {
            $settings['getHeaders'] = $requestSpec['headers'];
        }

        $history = [];
        if (!empty($requestSpec['history']['enabled'])) {
            if (!empty($requestSpec['history']['name'])) {
                $history['name'] = $requestSpec['history']['name'];
            }
        }

        $body = $requestSpec['body'];
        $payload = $body === '' ? null : $body;

        $hicurl = new Hicurl();
        return $hicurl->loadSingle(
            $requestSpec['url'],
            $payload,
            $settings,
            $history
        );
    }

    private function parseHeaders(string $headersRaw): array
    {
        $headers = [];
        $lines = preg_split("/\r\n|\n|\r/", $headersRaw) ?: [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            if ($name === '') {
                continue;
            }
            $headers[$name] = $value;
        }
        return $headers;
    }

    private function containsCookieHeader(string $headersRaw): bool
    {
        $lines = preg_split("/\r\n|\n|\r/", $headersRaw) ?: [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$name] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            if ($name !== '' && strcasecmp($name, 'cookie') === 0) {
                return true;
            }
        }
        return false;
    }

    private function applyCookiePreference(array $requestSpec, bool $includeCookies): array
    {
        if ($includeCookies) {
            return $requestSpec;
        }

        $filteredHeaders = [];
        foreach ($requestSpec['headers'] as $name => $value) {
            if (strcasecmp($name, 'cookie') === 0) {
                continue;
            }
            $filteredHeaders[$name] = $value;
        }

        $filteredHeaderLines = [];
        $headerLines = preg_split("/\r\n|\n|\r/", $requestSpec['headersRaw']) ?: [];
        foreach ($headerLines as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$name] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            if ($name !== '' && strcasecmp($name, 'cookie') === 0) {
                continue;
            }
            $filteredHeaderLines[] = $line;
        }

        $requestSpec['headers'] = $filteredHeaders;
        $requestSpec['headersRaw'] = implode("\n", $filteredHeaderLines);
        $requestSpec['hasCookies'] = false;

        return $requestSpec;
    }

    private function normalizeXpath(string $rawXpath): array
    {
        $xpathLines = preg_split("/\r\n|\n|\r/", $rawXpath) ?: [];
        $xpath = [];
        foreach ($xpathLines as $line) {
            $expression = trim($line);
            if ($expression !== '') {
                $xpath[] = $expression;
            }
        }
        return $xpath;
    }
}
