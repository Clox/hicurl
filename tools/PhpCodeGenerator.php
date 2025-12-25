<?php

class PhpCodeGenerator
{
    public static function fromRequestSpec(array $requestSpec): string
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

        $bodyCode = $requestSpec['body'] === ''
            ? 'null'
            : self::exportString($requestSpec['body']);

        $history = [];
        if (!empty($requestSpec['history']['enabled'])) {
            if (!empty($requestSpec['history']['name'])) {
                $history['name'] = $requestSpec['history']['name'];
            }
        }

        $code = "\$hicurl = new Hicurl();\n\n";
        $code .= '$settings = ' . self::formatArray($settings) . ";\n";
        $code .= '$history = ' . self::formatArray($history) . ";\n\n";
        $code .= "\$response = \$hicurl->loadSingle(\n";
        $code .= "\t" . self::exportString($requestSpec['url']) . ",\n";
        $code .= "\t" . $bodyCode . ",\n";
        $code .= "\t\$settings,\n";
        $code .= "\t\$history\n";
        $code .= ");\n";

        return $code;
    }

    private static function formatArray(array $array, int $indentLevel = 1): string
    {
        if ($array === []) {
            return '[]';
        }

        $indent = str_repeat("\t", $indentLevel);
        $closingIndent = str_repeat("\t", max(0, $indentLevel - 1));
        $lines = [];

        foreach ($array as $key => $value) {
            $lines[] = $indent . self::exportString((string)$key) . ' => ' . self::formatValue($value, $indentLevel) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . $closingIndent . "]";
    }

    private static function formatValue($value, int $indentLevel): string
    {
        if (is_array($value)) {
            return self::formatArray($value, $indentLevel + 1);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        return self::exportString((string)$value);
    }

    private static function exportString(string $value): string
    {
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
        return "'" . $escaped . "'";
    }
}
