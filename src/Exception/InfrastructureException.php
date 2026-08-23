<?php

declare(strict_types=1);

namespace Contempt\Core\Exception;

/**
 * An external dependency failed: database, broker, cache, remote service.
 *
 * Distinguished from {@see RuntimeException} because it is the category retry
 * and circuit-breaker policies classify on.
 */
class InfrastructureException extends \RuntimeException implements ContemptException {}
