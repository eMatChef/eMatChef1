<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/** Helferbedarf ist in sich widersprüchlich (z. B. Funktionen > Gesamtbedarf); wird als 422 ausgeliefert. */
final class HelperNeedValidationException extends \InvalidArgumentException
{
}
