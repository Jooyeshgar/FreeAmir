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

    private const DEFAULT_PER_PAGE = 50;

    /**
     * لیست سرفصل‌ها
     *
     * Query params:
     * - parent_id (optional): فقط فرزندان این سرفصل
     * - tree (optional, 0|1): درخت کامل (بدون pagination)
     * - q (optional): جستجو در نام یا کد
     * - page (optional): شماره صفحه (پیش‌فرض 1)
     * - per_page (optional): تعداد در هر صفحه (پیش‌فرض 50، حداکثر 100)
     */
    public function index(Request $request, $company): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'tree'      => ['nullable', 'boolean'],
            'q'         => ['nullable', 'string', 'max:100'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $baseQuery = Subject::query()->where('company_id', $company);

        // بررسی تعلق parent به همین شرکت
        if (isset($validated['parent_id'])) {
            $parentExists = (clone $baseQuery)
                ->where('id', $validated['parent_id'])
                ->exists();

            if (! $parentExists) {
                return response()->json([
                    'message' => 'سرفصل والد در این شرکت یافت نشد.',
                ], 404);
            }
        }

        // درخت کامل سرفصل‌ها (بدون pagination)
        if ($request->boolean('tree')) {
            $subjects = (clone $baseQuery)
                ->select(self::COLUMNS)
                ->orderBy('code')
                ->get();

            return response()->json(['data' => $this->buildTree($subjects)]);
        }

        // جستجو
        if (! empty($validated['q'])) {
            $q = trim($validated['q']);

            $paginator = (clone $baseQuery)
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                })
                ->orderBy('code')
                ->paginate($perPage, self::COLUMNS);

            return $this->paginatedResponse($paginator);
        }

        // لیست ریشه‌ها یا فرزندان
        $query = (clone $baseQuery)
            ->withCount(['children', 'transactions'])
            ->orderBy('code');

        if (isset($validated['parent_id'])) {
            $query->where('parent_id', $validated['parent_id']);
        } else {
            $query->whereIsRoot();
        }

        return $this->paginatedResponse($query->paginate($perPage, self::COLUMNS));
    }

    /**
     * جزئیات یک سرفصل
     */
    public function show(Request $request, $company, $id): JsonResponse
    {
        $subject = Subject::query()
            ->with(['parent:id,name,code,type', 'children:id,name,code,parent_id,type'])
            ->withCount(['children', 'transactions'])
            ->where('company_id', $company)
            ->where('id', $id)
            ->firstOrFail([
                'id', 'name', 'code', 'parent_id', 'type', 'is_permanent',
                'created_at', 'updated_at',
            ]);

        return response()->json([
            'data' => array_merge($this->formatSubject($subject), [
                'created_at' => $subject->created_at,
                'updated_at' => $subject->updated_at,
                'parent'     => $subject->parent ? $this->formatSubject($subject->parent) : null,
                'children'   => $subject->children->map(fn ($c) => $this->formatSubject($c))->values(),
            ]),
        ]);
    }

    /**
     * خروجی صفحه‌بندی‌شده با meta یکدست.
     */
    private function paginatedResponse(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn ($s) => $this->formatSubject($s))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * یک سرفصل را به فرمت یکدست API تبدیل می‌کند (type به‌صورت متن).
     */
    private function formatSubject($subject): array
    {
        $attributes = $subject->getAttributes();

        $data = [
            'id'           => $subject->id,
            'name'         => $subject->name,
            'code'         => $subject->code,
            'parent_id'    => $subject->parent_id,
            'type'         => $subject->type?->valueName(),
            'is_permanent' => (bool) ($attributes['is_permanent'] ?? false),
        ];

        if (array_key_exists('children_count', $attributes)) {
            $data['children_count'] = (int) $attributes['children_count'];
        }

        if (array_key_exists('transactions_count', $attributes)) {
            $data['transactions_count'] = (int) $attributes['transactions_count'];
        }

        return $data;
    }

    private function buildTree($subjects): array
    {
        $grouped = $subjects->groupBy(fn ($s) => empty($s->parent_id) ? 'root' : (string) $s->parent_id);

        $build = function (string $parentKey) use (&$build, $grouped): array {
            return $grouped->get($parentKey, collect())
                ->map(fn ($s) => $this->formatSubject($s) + ['children' => $build((string) $s->id)])
                ->values()
                ->all();
        };

        return $build('root');
    }
}