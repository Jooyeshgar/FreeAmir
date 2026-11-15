<?php

use App\Http\Middleware\EnsureMcpAccess;
use App\Http\Middleware\ForceMcpJsonResponse;
use App\Mcp\Server\AccountingServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/api/mcp', AccountingServer::class)
    ->middleware([ForceMcpJsonResponse::class, 'auth:sanctum', EnsureMcpAccess::class])
    ->name('mcp.handle');
