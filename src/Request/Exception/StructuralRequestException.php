<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request\Exception;

/**
 * The input cannot be assembled into the target type at all: wrong field type,
 * missing required field, unexpected field in a closed shape.
 *
 * Typically indicates a bug in the caller rather than a correctable user input,
 * so applications usually map this to HTTP 400 (Bad Request).
 */
final class StructuralRequestException extends InvalidRequestException
{
}
