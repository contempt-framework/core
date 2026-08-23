<?php

declare(strict_types=1);

namespace Contempt\Core\Exception;

/**
 * A framework runtime failure: lifecycle violations, stale build artifacts,
 * unsupported ABI, resolution outside an active scope.
 */
class RuntimeException extends \RuntimeException implements ContemptException {}
