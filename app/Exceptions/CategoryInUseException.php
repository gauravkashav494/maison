<?php

namespace App\Exceptions;

use RuntimeException;

/** A category can't go to the trash while it still has sub-categories, or come back while its parent is in the trash. */
class CategoryInUseException extends RuntimeException {}
