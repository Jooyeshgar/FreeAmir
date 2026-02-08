<?php

namespace App\Http\Controllers;

use App\Enums\ConfigTitle;
use App\Models\Config;
use App\Models\Subject;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct() {}

    public function index()
    {
        $configsTitle = array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], ConfigTitle::cases());
        $configs = Config::all();
        $subjects = Subject::all();

        return view('configs.index', compact('subjects', 'configs', 'configsTitle'));
    }

    public function edit($key)
    {
        $config = Config::where('key', $key)->first();

        // If config doesn't exist, create a new instance (not saved yet)
        if (! $config) {
            $config = new Config;
            $config->company_id = getActiveCompany();
            $config->key = $key;
            $config->value = 0;
            $config->type = '2';
            $config->category = '1';
            $config->desc = ConfigTitle::from(strtoupper($key))->label();
            $config->save();
        }
        $selectedSubjectId = (int) (config('amir.'.$config->key) ?: $config->value);
        $selectedSubject = $selectedSubjectId ? Subject::find($selectedSubjectId) : null;
        $subjects = $this->buildSubjectOptionsForSelectBox($selectedSubject);

        return view('configs.edit', compact('subjects', 'config', 'selectedSubject'));
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|exists:subjects,code|numeric',
            'key' => 'required|string',
        ]);

        $subject_id = Subject::where('code', $validatedData['code'])->first()->id;

        $config = Config::where('key', $validatedData['key'])->first();

        $config->value = (string) $subject_id;
        $config->update();

        return redirect()->route('configs.index')->with('success', __('Config updated successfully.'));
    }

    private function buildSubjectOptionsForSelectBox(?Subject $selectedSubject): array
    {
        $roots = Subject::whereIsRoot()->orderBy('code')->get();
        $selectedRootId = $selectedSubject ? $this->resolveRootId($selectedSubject) : null;

        if ($roots->count() <= 5) {
            $selectedRoots = $roots;
        } else {
            $selectedRoots = $roots->take(5);

            if ($selectedRootId && ! $selectedRoots->contains('id', $selectedRootId)) {
                $selectedRoots = $roots->take(4);
                $selectedRoot = $roots->firstWhere('id', $selectedRootId)
                    ?? Subject::find($selectedRootId);

                if ($selectedRoot) {
                    $selectedRoots = $selectedRoots->push($selectedRoot);
                }
            }
        }

        return $selectedRoots->map(fn (Subject $root) => $this->buildSubjectTree($root))->values()->all();
    }

    private function resolveRootId(Subject $subject): int
    {
        $current = $subject;

        while ($current->parent_id) {
            $current = $current->parent;

            if (! $current) {
                break;
            }
        }

        return (int) $current->id;
    }

    private function buildSubjectTree(Subject $subject): array
    {
        $children = $subject->children()->orderBy('code')->get();

        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'parent_id' => $subject->parent_id,
            'children' => $children->map(fn (Subject $child) => $this->buildSubjectTree($child))->values()->all(),
        ];
    }
}
