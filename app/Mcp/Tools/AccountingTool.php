<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

abstract class AccountingTool extends Tool
{
    /** Each group requires all its permissions; any matching group permits access. */
    protected array $permissionGroups = [];

    public function shouldRegister(): bool
    {
        $context = ContextResolver::authContext();
        if (! $context?->userCan('api.access')) {
            return false;
        }

        foreach ($this->permissionGroups as $permissions) {
            if (collect($permissions)->every(fn (string $permission) => $context->can($permission))) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        $definition = parent::toArray();
        $definition['inputSchema']['additionalProperties'] = false;
        $definition['annotations'] = [
            ...$definition['annotations'],
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'openWorldHint' => false,
        ];

        return $definition;
    }

    final public function handle(Request $request): Response
    {
        if (! $this->shouldRegister()) {
            return Response::error(__('Unauthorized access to this tool.'));
        }

        $rules = $this->rules();
        $validated = Validator::make(['arguments' => $request->all()], [
            'arguments' => ['array:'.implode(',', array_keys($rules))],
            ...collect($rules)->mapWithKeys(fn ($rule, $key) => ['arguments.'.$key => $rule])->all(),
        ])->validate()['arguments'];

        try {
            return ContextResolver::withContext($validated, fn () => $this->report($validated));
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return Response::error(__('Failed to retrieve report. Please try again or contact support.'));
        }
    }

    protected function success(array $data): Response
    {
        return Response::text(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    protected function error(string $message): Response
    {
        return Response::error($message);
    }

    abstract protected function rules(): array;

    abstract protected function report(array $arguments): Response;
}
