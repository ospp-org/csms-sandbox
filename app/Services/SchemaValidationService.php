<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\ValidationResult;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Ospp\Protocol\SchemaPath;

final class SchemaValidationService
{
    private ?Validator $validator = null;

    /** @var array<string, string|false> */
    private array $pathCache = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function validate(string $action, string $messageType, array $payload): ValidationResult
    {
        $schemaPath = $this->resolveSchemaPath($action, $messageType);

        if ($schemaPath === null) {
            return ValidationResult::skipped("No schema for {$action}/{$messageType}");
        }

        return $this->doValidate($schemaPath, $payload);
    }

    public function validateOutbound(string $action, mixed $payload): ValidationResult
    {
        $schemaPath = $this->resolveSchemaPath($action, 'Request');

        if ($schemaPath === null) {
            return ValidationResult::skipped("No outbound schema for {$action}");
        }

        return $this->doValidate($schemaPath, is_array($payload) ? $payload : []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOutboundSchema(string $action): ?array
    {
        $schemaPath = $this->resolveSchemaPath($action, 'Request');

        if ($schemaPath === null) {
            return null;
        }

        $content = file_get_contents($schemaPath);

        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function doValidate(string $schemaPath, array $payload): ValidationResult
    {
        $validator = $this->getValidator();
        $data = self::toJsonObject($payload);

        $content = file_get_contents($schemaPath);

        if ($content === false) {
            return ValidationResult::skipped("Cannot read schema: {$schemaPath}");
        }

        $schemaObj = json_decode($content);
        $result = $validator->validate($data, $schemaObj);

        if ($result->isValid()) {
            return ValidationResult::valid();
        }

        $formatter = new ErrorFormatter();
        $formatted = $formatter->format($result->error());

        $errors = [];
        foreach ($formatted as $path => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'path' => $path,
                    'message' => $message,
                    'keyword' => 'schema',
                ];
            }
        }

        return ValidationResult::invalid($errors);
    }

    private function resolveSchemaPath(string $action, string $messageType): ?string
    {
        $cacheKey = "{$action}/{$messageType}";

        if (isset($this->pathCache[$cacheKey])) {
            $cached = $this->pathCache[$cacheKey];

            return $cached === false ? null : $cached;
        }

        $dir = SchemaPath::directory() . '/mqtt';
        $kebab = self::toKebabCase($action);
        $suffix = strtolower($messageType);

        // Try with messageType suffix first (e.g. boot-notification-request)
        $path = "{$dir}/{$kebab}-{$suffix}.schema.json";

        if (file_exists($path)) {
            $this->pathCache[$cacheKey] = $path;

            return $path;
        }

        // Fallback without suffix (events, response-named actions)
        $path = "{$dir}/{$kebab}.schema.json";

        if (file_exists($path)) {
            $this->pathCache[$cacheKey] = $path;

            return $path;
        }

        $this->pathCache[$cacheKey] = false;

        return null;
    }

    private static function toKebabCase(string $pascalCase): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $pascalCase));
    }

    private static function toJsonObject(mixed $data): mixed
    {
        if (is_array($data)) {
            if ($data === [] || ! array_is_list($data)) {
                $obj = new \stdClass();
                foreach ($data as $key => $value) {
                    $obj->{$key} = self::toJsonObject($value);
                }

                return $obj;
            }

            return array_map(self::toJsonObject(...), $data);
        }

        return $data;
    }

    private function getValidator(): Validator
    {
        if ($this->validator === null) {
            $this->validator = new Validator();
            $this->validator->setMaxErrors(10);

            $this->validator->resolver()->registerPrefix(
                'https://ospp-standard.org/schemas/v1/',
                SchemaPath::directory() . '/'
            );
        }

        return $this->validator;
    }
}
