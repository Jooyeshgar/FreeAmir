<?php

namespace App\Http\Controllers;

use App\Enums\ConfigTitle;
use App\Models\Config;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct(private readonly SubjectService $subjectService) {}

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
        $selectedRootId = $selectedSubject ? $this->resolveRootId($selectedSubject) : null;
        $subjects = $this->subjectService->buildSubjectTreeForRootSelection($selectedRootId);

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

    private function resolveRootId(Subject $subject): int
    {
        $current = $subject;
        $visited = [];

        while ($current && $current->parent_id) {
            if (isset($visited[$current->id])) {
                break;
            }

            $visited[$current->id] = true;
            $current = $current->parent;
        }

        return (int) ($current?->id ?? $subject->id);
    }
}
