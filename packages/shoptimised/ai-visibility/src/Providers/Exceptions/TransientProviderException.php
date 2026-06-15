<?php

namespace Shoptimised\AiVisibility\Providers\Exceptions;

use RuntimeException;

/**
 * Thrown for transient provider failures (HTTP 429 / 5xx) that should be
 * retried with backoff by RunVisibilityPromptJob, rather than recorded as a
 * terminal failed result. Terminal errors (bad key, bad request) return a
 * failed AiProviderResponse instead.
 */
class TransientProviderException extends RuntimeException {}
