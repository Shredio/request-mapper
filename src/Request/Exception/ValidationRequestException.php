<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request\Exception;

/**
 * The input has the right shape but its content violates a constraint
 * (range, length, allowed values, format, ...).
 *
 * These are correctable user inputs, so applications usually map this to
 * HTTP 422 (Unprocessable Entity).
 */
final class ValidationRequestException extends InvalidRequestException
{
}
