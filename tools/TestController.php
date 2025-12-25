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
        ];
    }

    public function handle(array $post): array
    {
        $requestSpec = $this->buildRequestSpec($post);
        $result = $this->execute($requestSpec);
        $phpCode = PhpCodeGenerator::fromRequestSpec($requestSpec);

        return [
            'requestSpec' => $requestSpec,
            'result' => $result,
            'phpCode' => $phpCode,
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

        return [
            'url' => isset($post['url']) ? (string)$post['url'] : '',
            'method' => isset($post['method']) ? strtoupper((string)$post['method']) : 'GET',
            'headersRaw' => $headersRaw,
            'headers' => $this->parseHeaders($headersRaw),
            'body' => isset($post['body']) ? (string)$post['body'] : '',
            'settings' => $settings,
            'history' => [
                'enabled' => isset($post['history_enabled']),
                'name' => $historyName !== '' ? $historyName : null,
            ],
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
