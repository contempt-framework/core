<?php

declare(strict_types=1);

namespace Contempt\Core\Exception;

/**
 * Invalid, missing or contradictory configuration.
 *
 * Never used for values that are merely absent at build time and supplied at
 * runtime; that distinction is what makes a fail-fast boot meaningful.
 */
class ConfigurationException extends \RuntimeException implements ContemptException {}
