<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * This controller works with models that use the Query trait and have the SearchInModel scope.
 */
class ModelSelectController extends Controller
{
    /**
     * Build a hierarchy path from root to the direct parent of a subject. Dynamically loads all parent levels without depth limitation.
     *
     * @param  mixed  $subject  The subject instance (must have parent relationship)
     * @param  bool  $selectableGroups  Whether groups should be selectable (includes value)
     * @return array Array of hierarchy items with label and code
     */
    protected function buildHierarchyPath($subject, bool $selectableGroups = false): array
    {
        $path = [];
        $ancestors = $this->loadAllAncestors($subject);

        foreach ($ancestors as $ancestor) {
            $path[] = [
                'label' => $ancestor->name,
                'code' => isset($ancestor->code) ? formatCode($ancestor->code) : null,
                'value' => $selectableGroups ? (string) $ancestor->id : null,
            ];
        }

        return $path;
    }

    /**
     * Recursively load all ancestors of a subject from child to root.
     *
     * @param  mixed  $subject  The subject instance
     * @return array Array of ancestor models
     */
    protected function loadAllAncestors($subject): array
    {
        $ancestors = [];
        $current = $subject;

        // Traverse up the parent chain, loading parents dynamically
        while ($current && isset($current->parent_id) && $current->parent_id) {
            // Load parent if not already loaded
            if (! $current->relationLoaded('parent')) {
                $current->load('parent');
            }

            $parent = $current->parent;

            if ($parent) {
                // Add to beginning of array to maintain root-to-parent order
                array_unshift($ancestors, $parent);
                $current = $parent;
            } else {
                break;
            }
        }

        return $ancestors;
    }

    /**
     * Recursively add all descendants of a subject to the results collection.
     *
     * @param  mixed  $subject  The subject instance
     * @param  \Illuminate\Support\Collection  $results  The collection to add descendants to
     */
    protected function addAllDescendants($subject, &$results): void
    {
        // Load children if not already loaded
        if (! $subject->relationLoaded('children')) {
            $subject->load('children.parent');
        }

        foreach ($subject->children as $child) {
            // Add this child to results
            $results->push($child);

            // Recursively add its children
            if ($child->hasChildren()) {
                $this->addAllDescendants($child, $results);
            }
        }
    }

    public function __invoke(Request $request)
    {
        $modelClass = "App\Models\\".$request->get('model');
        $query = $request->get('q') ?? '';
        $limit = (int) ($request->get('limit') ?? 10);
        $labelField = $request->get('labelField') ?? 'name';
        $searchFields = $request->get('searchFields') ?? null;
        $orderBy = $request->get('orderBy') ?? 'id';
        $direction = $request->get('direction') ?? 'asc';
        $selectableGroups = $request->get('selectableGroups') ? true : false;

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
                    $field = trim($field);
                    // Handle ID field specially - exact match
                    if ($field === 'id' && is_numeric($query)) {
                        $q->orWhere('id', '=', $query);
                    } elseif ($field !== 'code') {
                        $q->orWhere($field, 'like', "%{$query}%");
                    }
                }
            });
        }

        // Load immediate parent only - hierarchy will be built dynamically as needed
        if ($modelClass === 'App\Models\Subject') {
            $queryBuilder->with('parent');
        } elseif (method_exists($model, 'subject')) {
            $queryBuilder->with('subject.parent');
        }

        $results = $queryBuilder->get();

        // For Subject model, expand results to include children when a parent matches
        if ($modelClass === 'App\Models\Subject' && $query) {
            $expandedResults = collect();
            foreach ($results as $subject) {
                // Add the matching subject itself
                $expandedResults->push($subject);

                // If this subject has children, include them recursively
                if ($subject->hasChildren()) {
                    $this->addAllDescendants($subject, $expandedResults);
                }
            }
            $results = $expandedResults;
        }

        // Format results for select box
        return response()->json($results->map(function ($item) use ($labelField, $modelClass, $selectableGroups) {
            // Try to get label from specified field, fallback to name, then code
            $label = $item->{$labelField} ?? $item->name ?? $item->code ?? $item->id;

            // Initialize code and group variables
            $code = null;
            $parentCode = null;
            $parentName = null;
            $parentValue = null;
            $hierarchy = [];

            // Handle Subject model specifically
            if ($modelClass === 'App\Models\Subject') {
                $code = isset($item->code) ? formatCode($item->code) : null;

                // Build hierarchy path from root to direct parent
                $hierarchy = $this->buildHierarchyPath($item, $selectableGroups);

                if ($item->parent) {
                    $parentName = $item->parent->name;
                    $parentCode = isset($item->parent->code) ? formatCode($item->parent->code) : null;
                    $parentValue = $selectableGroups ? (string) $item->parent->id : null;
                }
            } else {
                // Handle models that have a subject relationship
                if (isset($item->subject)) {
                    $code = isset($item->subject->code) ? formatCode($item->subject->code) : null;

                    // Build hierarchy path from the subject
                    $hierarchy = $this->buildHierarchyPath($item->subject, $selectableGroups);

                    $isItemAGroupItself = str_contains($modelClass, 'Group');

                    // If the item is a group itself, get its parent from subjects; otherwise, get its group or related group
                    $itemGroup = $isItemAGroupItself
                                        ? $item->subject->parent ?? null
                                        : $item->group ?? $item->{$modelClass.'Group'} ?? null;

                    if ($itemGroup) {
                        $parentCode = isset($itemGroup->code) ? formatCode($itemGroup->code) : (isset($itemGroup->subject->code) ? formatCode($itemGroup->subject->code) : null);
                        $parentName = $itemGroup->name ?? $itemGroup->subject->name ?? null;
                        $parentValue = $selectableGroups ? (string) ($itemGroup->id ?? $itemGroup->subject->id ?? null) : null;
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
                    'value' => $parentValue ?? null,
                ] : null,
                'hierarchy' => $hierarchy,
            ];
        }));
    }
}
