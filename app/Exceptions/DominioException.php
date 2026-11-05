<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de regla de negocio con mensaje legible para el usuario (español).
 * bootstrap/app.php la convierte en redirect back() con flash `error` (o 422 JSON).
 */
class DominioException extends RuntimeException {}
