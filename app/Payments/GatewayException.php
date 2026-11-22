<?php

namespace App\Payments;

use RuntimeException;

/** The provider rejected a request or could not be reached. The message is safe to show to staff. */
class GatewayException extends RuntimeException {}
