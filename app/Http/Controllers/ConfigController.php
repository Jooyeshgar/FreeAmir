<?php

namespace App\Http\Controllers;

use App\Enums\ConfigTitle;
use App\Models;
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
        $configs = Models\Config::all();
        $subjects = Models\Subject::all();

        return view('configs.index', compact('subjects', 'configs', 'configsTitle'));
    }

    public function edit($key)
    {
        $config = Models\Config::where('key', $key)->first();

        // If config doesn't exist, create a new instance (not saved yet)
        if (! $config) {
            $config = new Models\Config;
            $config->company_id = getActiveCompany();
            $config->key = $key;
            $config->value = 0;
            $config->type = '2';
            $config->category = '1';
            $config->desc = ConfigTitle::from(strtoupper($key))->label();
            $config->save();
        }
        $subjects = Models\Subject::all();

        return view('configs.edit', compact('subjects', 'config'));
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|exists:subjects,code|numeric',
            'key' => 'required|string',
        ]);

        $subject_id = Subject::where('code', $validatedData['code'])->first()->id;

        $config = Models\Config::where('key', $validatedData['key'])->first();

        $config->value = (string) $subject_id;
        $config->update();

        return redirect()->route('configs.index')->with('success', __('Config updated successfully.'));
    }
}
