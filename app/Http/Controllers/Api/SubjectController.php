<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SubjectController extends Controller
{
    private const COLUMNS = ['id', 'name', 'code', 'parent_id', 'type', 'is_permanent'];

    public function index(Request $request, int $fiscalYear): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'tree' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);
        $baseQuery = Subject::query();

        if (isset($validated['parent_id']) && ! $baseQuery->whereKey($validated['parent_id'])->exists()) {
            return response()->json(['message' => __('The parent subject was not found in this fiscal year.')], 404);
        }

        if ($request->boolean('tree')) {
            $subjects = (clone $baseQuery)->select(self::COLUMNS)->orderBy('code')->get();

            return response()->json(['data' => $this->buildTree($subjects)]);
        }

        if (! empty($validated['q'])) {
            $search = trim($validated['q']);
            $paginator = (clone $baseQuery)
                ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                ->orderBy('code')->paginate($perPage, self::COLUMNS);

            return $this->paginatedResponse($paginator);
        }

        $query = (clone $baseQuery)->withCount(['children', 'transactions'])->orderBy('code');
        isset($validated['parent_id'])
            ? $query->where('parent_id', $validated['parent_id'])
            : $query->whereIsRoot();

        return $this->paginatedResponse($query->paginate($perPage, self::COLUMNS));
    }

    public function show(Request $request, int $fiscalYear, int $id): JsonResponse
    {
        $subject = Subject::query()
            ->with(['parent:id,name,code,type', 'children:id,name,code,parent_id,type'])
            ->withCount(['children', 'transactions'])
            ->findOrFail($id);

        return response()->json([
            'data' => array_merge($this->formatSubject($subject), [
                'created_at' => $subject->created_at,
                'updated_at' => $subject->updated_at,
                'parent' => $subject->parent ? $this->formatSubject($subject->parent) : null,
                'children' => $subject->children->map(fn ($child) => $this->formatSubject($child))->values(),
            ]),
        ]);
    }

    private function paginatedResponse(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())->map(fn ($subject) => $this->formatSubject($subject))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function formatSubject(Subject $subject): array
    {
        $attributes = $subject->getAttributes();

        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'parent_id' => $subject->parent_id,
            'type' => $subject->type?->valueName(),
            'is_permanent' => (bool) ($attributes['is_permanent'] ?? false),
            'children_count' => isset($attributes['children_count']) ? (int) $attributes['children_count'] : null,
            'transactions_count' => isset($attributes['transactions_count']) ? (int) $attributes['transactions_count'] : null,
        ];
    }

    private function buildTree($subjects): array
    {
        $grouped = $subjects->groupBy(fn ($subject) => empty($subject->parent_id) ? 'root' : (string) $subject->parent_id);
        $build = function (string $parentKey) use (&$build, $grouped): array {
            return $grouped->get($parentKey, collect())
                ->map(fn ($subject) => $this->formatSubject($subject) + ['children' => $build((string) $subject->id)])
                ->values()->all();
        };

        return $build('root');
    }
}
