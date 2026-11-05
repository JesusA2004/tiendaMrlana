<?php

namespace App\Services\Pdf;

use App\Exceptions\DominioException;

/**
 * No se pudo generar el PDF (Node/Chrome no disponibles o fallo del render).
 * Extiende DominioException: bootstrap/app.php la convierte en flash `error`.
 */
class PdfNoDisponibleException extends DominioException {}
