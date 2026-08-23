<?php

declare(strict_types=1);

namespace Contempt\Core\Exception;

/**
 * Marker for every failure originating in framework code.
 *
 * An interface rather than a base class, so packages may extend the most
 * accurate SPL exception (`\InvalidArgumentException`, `\LogicException`) and
 * still be catchable as a framework error.
 */
interface ContemptException extends \Throwable {}
