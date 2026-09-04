<?php

namespace App\Services\ConnectWise;

use Exception;

/**
 * Thrown for any failed ConnectWise Manage API call. Callers in the
 * checkout flow catch this specifically so they can fall back to the local
 * sync queue instead of blocking the sale — see App\Jobs\PushSolutionsListToConnectWise.
 */
class ConnectWiseException extends Exception
{
    public function __construct(string $message, public readonly ?int $statusCode = null, public readonly mixed $responseBody = null)
    {
        parent::__construct($message);
    }
}
