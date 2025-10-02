<?php

namespace App\Http\Controllers;

use App\Models;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $configs = Models\Config::all();
        $subjects = Models\Subject::all();
        return view('configs.index', compact('subjects', 'configs'));
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


    public function edit(Models\Config $config)
    {
        // $subjects = Models\Subject::whereNull('parent_id')->get();
        $subjects = Models\Subject::all();
        return view('configs.edit', compact('subjects', 'config'));
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'cust_subject' => 'nullable|exists:subjects,id|numeric',
            'cash_book' => 'nullable|exists:subjects,id|numeric',
            'income' => 'nullable|exists:subjects,id|numeric',
            'bank' => 'nullable|exists:subjects,id|numeric',
            'cash' => 'nullable|exists:subjects,id|numeric',
            'buy_discount' => 'nullable|exists:subjects,id|numeric',
            'sell_discount' => 'nullable|exists:subjects,id|numeric',
            'sell_vat' => 'nullable|exists:subjects,id|numeric',
            'buy_vat' => 'nullable|exists:subjects,id|numeric',
            'product' => 'nullable|exists:subjects,id|numeric',
            'sell_free' => 'nullable|exists:subjects,id|numeric',
            'code' => 'required|exists:subjects,code|string',
        ]);

        foreach ($validatedData as $key => $value) {
            if ($key != 'code') {
                $config = Models\Config::where('key', $key)->first();
            }
        }

        $subject_id = Subject::where('code', $validatedData['code'])->first()->id;
        $config['value'] = (string) $subject_id;
        $config->update();

        return redirect()->route('configs.index')->with('success', __('Config updated successfully.'));
    }
}
