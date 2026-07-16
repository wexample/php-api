<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Helper;

use Wexample\PhpApi\Exceptions\ApiEnvelopeException;

/**
 * Standard envelope produced by wexample/symfony-api controllers
 * (AbstractApiController::apiResponse): {type, code, message?, data}.
 */
final class ApiEnvelopeHelper
{
    final public const RESPONSE_TYPE_SUCCESS = 'success';
    final public const RESPONSE_TYPE_ERROR = 'error';

    /**
     * Returns the envelope "data" payload, throwing ApiEnvelopeException on
     * malformed envelopes or explicit error responses, so callers never have
     * to retest type === "success" themselves.
     */
    public static function unwrap(array $response): mixed
    {
        if (($response['type'] ?? null) === self::RESPONSE_TYPE_ERROR) {
            $message = $response['message'] ?? null;

            throw new ApiEnvelopeException(
                message: is_string($message) && $message !== '' ? $message : 'ERR_UNDEFINED',
                responseCode: is_int($response['code'] ?? null) ? $response['code'] : null,
                envelope: $response,
            );
        }

        if (! array_key_exists('data', $response)) {
            throw new ApiEnvelopeException(
                message: 'Invalid API response: missing "data" in envelope.',
                envelope: $response,
            );
        }

        return $response['data'];
    }
}
