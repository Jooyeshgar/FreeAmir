<?php

namespace App\Http\Controllers;

use App\Models;
use App\Models\Subject;
use App\Enums\ConfigTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $configsTitle = array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], ConfigTitle::cases());
        $configs = Models\Config::all();
        $subjects = Models\Subject::all();
        return view('configs.index', compact('subjects', 'configs', 'configsTitle'));
    }

    // public function store(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'cust_subject' => 'nullable|exists:subjects,id|numeric',
    //         'cash_book' => 'nullable|exists:subjects,id|numeric',
    //         'income' => 'nullable|exists:subjects,id|numeric',
    //         'bank' => 'nullable|exists:subjects,id|numeric',
    //         'cash' => 'nullable|exists:subjects,id|numeric',
    //         'buy_discount' => 'nullable|exists:subjects,id|numeric',
    //         'sell_discount' => 'nullable|exists:subjects,id|numeric',
    //         'sell_vat' => 'nullable|exists:subjects,id|numeric',
    //         'buy_vat' => 'nullable|exists:subjects,id|numeric',
    //         'product' => 'nullable|exists:subjects,id|numeric',
    //         'sell_free' => 'nullable|exists:subjects,id|numeric',
    //     ]);

    //     foreach ($validatedData as $key => $value) {
    //         if (empty($value)) {
    //             continue;
    //         }

    //         $config = Models\Config::where('key', $key)->first();
    //         if ($config !== null) {
    //             $config->update(['value' => $value]);
    //         } else {
    //             Models\Config::create([
    //                 'key' => $key,
    //                 'value' => $value,
    //                 'company_id' => session('active-company-id'),
    //                 'type' => '',
    //                 'category' => '',
    //             ]);
    //         }
    //     }

    //     return redirect()->route('configs.index')->with('success', __('Config created successfully.'));
    // }


    public function edit($key)
    {
        $config = Models\Config::where('key', $key)->first();
        
        // If config doesn't exist, create a new instance (not saved yet)
        if (!$config) {
            $config = new Models\Config();
            $config->company_id = session('active-company-id');
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
