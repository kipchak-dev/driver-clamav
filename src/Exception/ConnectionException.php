<?php

declare(strict_types=1);

namespace Kipchak\Driver\ClamAV\Exception;

use RuntimeException;

/**
 * Thrown when clamd cannot be reached or the socket exchange fails. Distinct
 * from a clean/infected result so callers can apply a fail-open/fail-closed
 * policy on scanner unavailability.
 */
final class ConnectionException extends RuntimeException
{
}
