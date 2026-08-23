<?php

declare(strict_types=1);

namespace Contempt\Core\Exception;

/**
 * A security invariant was violated.
 *
 * Messages MUST NOT contain the material that triggered them — no secret
 * value, no credential, no allow-list contents.
 */
class SecurityException extends \RuntimeException implements ContemptException {}
