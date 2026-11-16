<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

abstract class AccountingTool extends Tool
{
    /** Each group requires all its permissions; any matching group permits access. */
    protected array $permissionGroups = [];

    protected bool $readOnlyHint = true;

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
            'readOnlyHint' => $this->readOnlyHint,
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

        try {
            $rules = $this->rules();
            $validated = Validator::make(['arguments' => $request->all()], [
                'arguments' => ['array:'.implode(',', array_unique(array_map(fn ($key) => explode('.', $key)[0], array_keys($rules))))],
                ...collect($rules)->mapWithKeys(fn ($rule, $key) => ['arguments.'.$key => $rule])->all(),
            ])->validate()['arguments'];

            return ContextResolver::withContext($validated, fn () => $this->report($validated));
        } catch (ValidationException $exception) {
            return $this->error(json_encode([
                'message' => __('The given data was invalid.'),
                'errors' => $exception->errors(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return Response::error($this->readOnlyHint
                ? __('Failed to retrieve report. Please try again or contact support.')
                : __('Failed to create record. Please try again or contact support.'));
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

    /** Run the web form's normalization, rules and after-validation checks in the selected fiscal context. */
    protected function validateFormRequest(string $class, array $data): FormRequest
    {
        $request = $class::create('/', 'POST', $data);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => ContextResolver::authContext()->user);
        $request->validateResolved();

        return $request;
    }

    abstract protected function rules(): array;

    abstract protected function report(array $arguments): Response;
}
