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

        return view('configs.index', compact('configs', 'subjects', 'configs'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'cash_book' => 'nullable|exists:subjects,id|numeric',
            'income' => 'nullable|exists:subjects,id|numeric',
            'bank' => 'nullable|exists:banks,id|numeric',
            'cash' => 'nullable|numeric',
            'buy_discount' => 'nullable|numeric',
            'sell_discount' => 'nullable|numeric',
            'sell_vat' => 'nullable|numeric',
            'buy_vat' => 'nullable|numeric',
            'sell_free' => 'nullable|numeric',
        ]);

        // Upload file (Storage or FTP)
        $file = $request->file('co_logo');
        if ($file) {
            $extension = $file->getClientOriginalExtension();
            $uniqueName = uniqid() . '.' . $extension;
            $co_logo = Models\Config::where('key', 'co_logo')->first();

            if ($co_logo) {
                $oldPath = 'public/' . $co_logo->value;
                if (Storage::exists($oldPath)) {
                    Storage::delete($oldPath);
                }
            }

            $storagePath = 'public/company_logos/' . $uniqueName;
            Storage::put($storagePath, file_get_contents($file));
            $validatedData['co_logo'] = "company_logos/{$uniqueName}";
        }

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
