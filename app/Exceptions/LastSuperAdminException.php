<?php

namespace App\Exceptions;

use RuntimeException;

/** Raised when a change would leave the platform with no active Super Admin (see User::booted). */
class LastSuperAdminException extends RuntimeException {}
