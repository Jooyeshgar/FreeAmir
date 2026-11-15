<?php

namespace App\Console\Commands;

use App\Mcp\Auth\TokenAuthenticator;
use App\Mcp\Server\AccountingServer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Command;
use Laravel\Mcp\Server\Transport\StdioTransport;

class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve
        {--transport=stdio : Communication transport (stdio)}
        {--token= : Personal access token, or set FREEAMIR_MCP_TOKEN}';

    protected $description = 'Run the FreeAmir accounting MCP server over authenticated stdio';

    public function handle(TokenAuthenticator $authenticator): int
    {
        if (strtolower((string) $this->option('transport')) !== 'stdio') {
            fwrite(STDERR, "Unsupported transport. Use stdio.\n");

            return self::FAILURE;
        }

        $token = (string) ($this->option('token') ?: config('mcp-server.token'));
        $context = $authenticator->authenticate($token);
        if (! $context?->userCan('api.access')) {
            fwrite(STDERR, "A valid token and api.access permission are required.\n");

            return self::FAILURE;
        }

        $transport = new StdioTransport;
        $server = new AccountingServer($transport);
        $server->useToken($token);
        $server->start();

        try {
            $transport->run();
        } catch (AuthenticationException) {
            fwrite(STDERR, "Token or api.access permission is no longer valid.\n");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
