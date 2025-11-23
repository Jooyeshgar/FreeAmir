<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * This controller works with models that use the Query trait and have the SearchInModel scope.
 */
class ModelSelectController extends Controller
{
    public function __invoke(Request $request)
    {
        $modelClass = "App\Models\\".$request->get('model');
        $query = $request->get('q') ?? '';
        $limit = (int) ($request->get('limit') ?? 10);
        $labelField = $request->get('labelField') ?? 'name';
        $searchFields = $request->get('searchFields') ?? null;
        $orderBy = $request->get('orderBy') ?? 'id';
        $direction = $request->get('direction') ?? 'asc';

        if (! class_exists($modelClass)) {
            return response()->json([]);
        }

        $model = new $modelClass;

        if (! method_exists($model, 'scopeSearchInModel')) {
            return response()->json([]);
        }

        $queryBuilder = $modelClass::SearchInModel(
            searchQuery: $query,
            limit: $limit,
            orderBy: $orderBy,
            direction: $direction
        );

        // Override search fields if provided
        if ($searchFields && $query) {
            $fields = explode(',', $searchFields);
            $queryBuilder->where(function ($q) use ($fields, $query) {
                foreach ($fields as $field) {
                    if (trim($field) !== 'code') {
                        $q->orWhere(trim($field), 'like', "%{$query}%");
                    }
                }
            });
        }

        // Load relationships for subjects
        if ($modelClass === 'App\Models\Subject') {
            $queryBuilder->with('parent');
        } elseif (method_exists($model, 'subject')) {
            $queryBuilder->with('subject.parent');
        }

        $results = $queryBuilder->get();

        // Format results for select box
        return response()->json($results->map(function ($item) use ($labelField, $modelClass) {
            // Try to get label from specified field, fallback to name, then code
            $label = $item->{$labelField} ?? $item->name ?? $item->code ?? $item->id;

            // Initialize code and group variables
            $code = null;
            $parentCode = null;
            $parentName = null;

            // Handle Subject model specifically
            if ($modelClass === 'App\Models\Subject') {
                $code = isset($item->code) ? formatCode($item->code) : null;
                if ($item->parent) {
                    $parentName = $item->parent->name;
                    $parentCode = isset($item->parent->code) ? formatCode($item->parent->code) : null;
                }
            } else {
                // Handle models that have a subject relationship
                if (isset($item->subject)) {
                    $code = isset($item->subject->code) ? formatCode($item->subject->code) : null;

                    $isItemAGroupItself = str_contains($modelClass, 'Group');

                    // If the item is a group itself, get its parent from subjects; otherwise, get its group or related group
                    $itemGroup = $isItemAGroupItself
                                        ? $item->subject->parent ?? null
                                        : $item->group ?? $item->{$modelClass.'Group'} ?? null;

                    if ($itemGroup) {
                        $parentCode = isset($itemGroup->code) ? formatCode($itemGroup->code) : (isset($itemGroup->subject->code) ? formatCode($itemGroup->subject->code) : null);
                        $parentName = $itemGroup->name ?? $itemGroup->subject->name ?? null;
                    }
                }
            }

            return [
                'id' => $item->id,
                'value' => $item->id,
                'label' => $label,
                'code' => $code,
                'group' => ($parentName || $parentCode) ? [
                    'label' => $parentName,
                    'code' => $parentCode,
                ] : null,
            ];
        }));
    }
}
