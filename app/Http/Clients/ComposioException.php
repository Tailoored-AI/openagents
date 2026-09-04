<?php

namespace App\Http\Clients;

use Illuminate\Http\Client\HttpClientException;

/**
 * Thrown when Composio answers with a payload the application cannot use.
 */
class ComposioException extends HttpClientException
{
    //
}
