<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $subjects = Models\Subject::whereNull('parent_id')->get();
        $configs = Models\Config::all();
        $configs = $configs->pluck('value', 'key')->toArray();

        return view('configs.index', compact('configs', 'subjects'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'cash_book' => 'nullable|exists:subjects,id|numeric',
            'income' => 'nullable|exists:subjects,id|numeric',
            'bank' => 'nullable|exists:banks,id|numeric',
            'cash' => 'nullable|exists:subjects,id|numeric',
            'buy_discount' => 'nullable|exists:subjects,id|numeric',
            'sell_discount' => 'nullable|exists:subjects,id|numeric',
            'sell_vat' => 'nullable|exists:subjects,id|numeric',
            'buy_vat' => 'nullable|exists:subjects,id|numeric',
            'sell_free' => 'nullable|exists:subjects,id|numeric',
        ]);

        foreach ($validatedData as $key => $value) {
            if (empty($value)) {
                continue;
            }

            $config = Models\Config::where('key', $key)->first();
            if ($config !== null) {
                $config->update(['value' => $value]);
            } else {
                Models\Config::create([
                    'key' => $key,
                    'value' => $value,
                    'company_id' => session('active-company-id'),
                    'type' => '',
                    'category' => '',
                ]);
            }
        }

        return redirect()->route('configs.index')->with('success', __('Config created successfully.'));
    }
}
