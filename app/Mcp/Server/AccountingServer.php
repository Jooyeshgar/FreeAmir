<?php

namespace App\Mcp\Server;

use App\Mcp\Auth\TokenAuthenticator;
use App\Mcp\Support\ContextResolver;
use App\Mcp\Tools\ApproveInvoiceTool;
use App\Mcp\Tools\CreateCustomerTool;
use App\Mcp\Tools\CreateInvoiceTool;
use App\Mcp\Tools\CreateProductTool;
use App\Mcp\Tools\GetCurrentContextTool;
use App\Mcp\Tools\GetCustomerBalanceTool;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetInvoicesSummaryTool;
use App\Mcp\Tools\GetProfitLossTool;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Server;

class AccountingServer extends Server
{
    protected string $name = 'FreeAmir Accounting';

    protected string $version = '1.0.0';

    protected array $capabilities = [self::CAPABILITY_TOOLS => ['listChanged' => false]];

    protected array $tools = [
        GetCurrentContextTool::class,
        GetProfitLossTool::class,
        GetFinancialSummaryTool::class,
        GetInvoicesSummaryTool::class,
        GetCustomerBalanceTool::class,
        CreateCustomerTool::class,
        CreateProductTool::class,
        CreateInvoiceTool::class,
        ApproveInvoiceTool::class,
    ];

    private ?string $token = null;

    public function useToken(#[\SensitiveParameter] string $token): void
    {
        $this->token = $token;
    }

    public function handle(string $rawMessage): void
    {
        // Revalidate every stdio message, including token revocation and permission changes.
        $context = app(TokenAuthenticator::class)->authenticate($this->token ?? request()->bearerToken());
        if (! $context?->userCan('api.access')) {
            throw new AuthenticationException('A valid token and api.access permission are required.');
        }

        $this->instructions = config('mcp-server.instructions');
        ContextResolver::withAuthContext($context, fn () => parent::handle($rawMessage));
    }
}
