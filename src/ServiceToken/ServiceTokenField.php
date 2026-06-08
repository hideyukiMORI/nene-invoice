<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\ServiceApi\ServiceScope;
use NeneInvoice\Support\TextLimit;

/**
 * Parses + validates the service-token issuance payload (`POST
 * /admin/service-tokens`). Scopes must be a non-empty subset of the registered
 * {@see ServiceScope} values; the subject defaults to the NeNe Clear principal.
 */
final class ServiceTokenField
{
    public const DEFAULT_SUBJECT = 'service:clear';

    /** Issuance TTL bounds (seconds): 1 hour … 1 year, default 30 days. */
    public const MIN_TTL_SECONDS = 3600;
    public const MAX_TTL_SECONDS = 31_536_000;
    public const DEFAULT_TTL_SECONDS = 2_592_000;

    /**
     * @param array<string, mixed> $body
     *
     * @throws ValidationException
     */
    public static function parse(array $body): IssueServiceTokenInput
    {
        $errors = [];

        $label = $body['label'] ?? null;
        if (!is_string($label) || trim($label) === '') {
            $errors[] = new ValidationError('body.label', 'Label is required.', 'required');
            $label = '';
        } elseif (mb_strlen($label) > TextLimit::NAME) {
            $errors[] = new ValidationError('body.label', sprintf('Must be at most %d characters.', TextLimit::NAME), 'too_long');
        }

        $scopes = self::parseScopes($body['scopes'] ?? null, $errors);

        $subject = $body['subject'] ?? self::DEFAULT_SUBJECT;
        if (!is_string($subject) || trim($subject) === '') {
            $errors[] = new ValidationError('body.subject', 'Subject must be a non-empty string.', 'invalid');
            $subject = self::DEFAULT_SUBJECT;
        } elseif (mb_strlen($subject) > TextLimit::NAME) {
            $errors[] = new ValidationError('body.subject', sprintf('Must be at most %d characters.', TextLimit::NAME), 'too_long');
        }

        $ttl = $body['ttl_seconds'] ?? self::DEFAULT_TTL_SECONDS;
        if (!is_int($ttl) || $ttl < self::MIN_TTL_SECONDS || $ttl > self::MAX_TTL_SECONDS) {
            $errors[] = new ValidationError(
                'body.ttl_seconds',
                sprintf('TTL must be an integer between %d and %d seconds.', self::MIN_TTL_SECONDS, self::MAX_TTL_SECONDS),
                'invalid',
            );
            $ttl = self::DEFAULT_TTL_SECONDS;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new IssueServiceTokenInput(
            label: trim($label),
            scopes: $scopes,
            subject: trim($subject),
            ttlSeconds: $ttl,
        );
    }

    /**
     * @param list<ValidationError> $errors
     * @return list<string>
     */
    private static function parseScopes(mixed $value, array &$errors): array
    {
        if (!is_array($value) || $value === []) {
            $errors[] = new ValidationError('body.scopes', 'At least one scope is required.', 'required');

            return [];
        }

        $allowed = array_map(static fn (ServiceScope $s): string => $s->value, ServiceScope::cases());
        $scopes = [];

        foreach ($value as $scope) {
            if (!is_string($scope) || !in_array($scope, $allowed, true)) {
                $errors[] = new ValidationError('body.scopes', sprintf('Each scope must be one of: %s.', implode(', ', $allowed)), 'invalid');

                return [];
            }

            if (!in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }
}
