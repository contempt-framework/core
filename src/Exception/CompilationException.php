<?php

declare(strict_types=1);

namespace Contempt\Core\Exception;

/**
 * The Application Graph could not be built, validated or generated.
 *
 * Carries compiler diagnostics; the compiler reports them individually before
 * this is thrown, so it terminates a build rather than describing one error.
 */
class CompilationException extends \RuntimeException implements ContemptException {}
