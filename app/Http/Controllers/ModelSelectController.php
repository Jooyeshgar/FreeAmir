<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ModelSelectController extends Controller
{
    public function __invoke(Request $request)
    {
        $modelClass = $request->get('model');
        $query = $request->get('q', '');
        $limit = (int) $request->get('limit', 10);

        if (! class_exists($modelClass)) {
            return response()->json([]);
        }

        $model = new $modelClass;

        if (! method_exists($model, 'searchInModel')) {
            return response()->json([]);
        }

        $results = $model->searchInModel(
            searchQuery: $query,
            limit: $limit
        );

        return response()->json($results->map(fn ($item) => [
            'id' => $item->id,
            'value' => $item->id,
            'label' => $item->name,
        ]));
    }
}
