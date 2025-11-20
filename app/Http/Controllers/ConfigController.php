<?php

namespace App\Http\Controllers;

use App\Enums\ConfigTitle;
use App\Models;
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
        $configs = (new Models\Config)->getSome();
        $subjects = (new Models\Subject)->getSome();

        return view('configs.index', compact('subjects', 'configs', 'configsTitle'));
    }

    public function edit($key)
    {
        $config = Models\Config::where('key', $key)->first();

        if (! $config) {
            $config = new Models\Config;
            $config->company_id = session('active-company-id');
            $config->key = $key;
            $config->value = 0;
            $config->type = '2';
            $config->category = '1';
            $config->desc = ConfigTitle::from(strtoupper($key))->label();
            $config->save();
        }
        $subjects = (new Models\Subject)->getSome();

        return view('configs.edit', compact('subjects', 'config'));
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|exists:subjects,code|numeric',
            'key' => 'required|string',
        ]);

        $subject_id = Models\Subject::where('code', $validatedData['code'])->first()->id;

        $config = Models\Config::where('key', $validatedData['key'])->first();

        $config->value = (string) $subject_id;
        $config->update();

        return redirect()->route('configs.index')->with('success', __('Config updated successfully.'));
    }
}
