<?php

class CurlImporter
{
    public static function fromCurl(string $curl): array
    {
        $normalized = self::normalize($curl);
        $requestSpec = self::emptyRequestSpec();

        if ($normalized === '') {
            return $requestSpec;
        }

        $tokens = self::tokenize($normalized);

        $explicitMethod = null;
        $hasDataFlag = false;
        $body = '';
        $headersList = [];
        $headersAssoc = [];
        $url = '';

        $total = count($tokens);
        for ($i = 0; $i < $total; $i++) {
            $token = $tokens[$i];

            if ($i === 0 && strtolower($token) === 'curl') {
                continue;
            }

            if (self::isRequestFlag($token)) {
                $value = self::nextTokenValue($tokens, $i);
                if ($value !== null) {
                    $explicitMethod = strtoupper(self::unquote($value));
                    $i++;
                }
                continue;
            }

            if (self::isHeaderFlag($token)) {
                $value = self::nextTokenValue($tokens, $i);
                if ($value !== null) {
                    $headerLine = self::unquote($value);
                    if ($headerLine !== '') {
                        $headersList[] = $headerLine;
                        [$name, $headerValue] = array_map('trim', explode(':', $headerLine, 2)) + [1 => ''];
                        if ($name !== '') {
                            $headersAssoc[$name] = $headerValue;
                        }
                    }
                    $i++;
                }
                continue;
            }

            if (self::isDataFlag($token)) {
                $value = self::nextTokenValue($tokens, $i);
                if ($value !== null) {
                    $hasDataFlag = true;
                    $body = self::unquote($value);
                    $i++;
                }
                continue;
            }

            if ($url === '' && !self::isFlag($token)) {
                $url = self::unquote($token);
            }
        }

        $hasCookies = self::hasCookieHeader($headersAssoc);

        $requestSpec['url'] = $url;
        $requestSpec['method'] = $explicitMethod ?? ($hasDataFlag ? 'POST' : 'GET');
        $requestSpec['headersRaw'] = implode("\n", $headersList);
        $requestSpec['headers'] = $headersAssoc;
        $requestSpec['body'] = $body;
        $requestSpec['settings']['jsonPayload'] = self::detectJsonPayload($headersAssoc);
        $requestSpec['settings']['jsonResponse'] = self::detectJsonResponse($headersAssoc);
        $requestSpec['hasCookies'] = $hasCookies;

        return $requestSpec;
    }

    private static function normalize(string $curl): string
    {
        $curl = preg_replace("/\\\\\r?\n/", '', $curl) ?? $curl;
        return trim($curl);
    }

    private static function tokenize(string $curl): array
    {
        preg_match_all('/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|\\S+/', $curl, $matches);
        $tokens = $matches[0] ?? [];

        return array_values(array_filter(array_map('trim', $tokens), static function ($token) {
            return $token !== '';
        }));
    }

    private static function unquote(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2 && (($value[0] === "'" && $value[$length - 1] === "'") || ($value[0] === '"' && $value[$length - 1] === '"'))) {
            $value = substr($value, 1, -1);
        }

        return str_replace(['\\\\', "\\'", '\\"'], ['\\', "'", '"'], $value);
    }

    private static function nextTokenValue(array $tokens, int $index): ?string
    {
        return $tokens[$index + 1] ?? null;
    }

    private static function isFlag(string $token): bool
    {
        return strpos($token, '-') === 0;
    }

    private static function isHeaderFlag(string $token): bool
    {
        return in_array($token, ['-H', '--header'], true);
    }

    private static function isRequestFlag(string $token): bool
    {
        return in_array($token, ['-X', '--request'], true);
    }

    private static function isDataFlag(string $token): bool
    {
        return in_array($token, ['--data', '--data-raw', '--data-binary', '--data-urlencode', '-d'], true);
    }

    private static function hasCookieHeader(array $headers): bool
    {
        foreach ($headers as $name => $_value) {
            if (strcasecmp($name, 'cookie') === 0) {
                return true;
            }
        }
        return false;
    }

    private static function detectJsonPayload(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'content-type') === 0 && stripos($value, 'application/json') !== false) {
                return true;
            }
        }
        return false;
    }

    private static function detectJsonResponse(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'accept') === 0 && stripos($value, 'application/json') !== false) {
                return true;
            }
        }
        return false;
    }

    private static function emptyRequestSpec(): array
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
}
