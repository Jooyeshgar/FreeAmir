<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    public function index()
    {
        $configs = Models\Config::paginate(12);
        return view('configs.index', compact('configs'));
    }

    public function create()
    {
        $subjects = Models\Subject::all();
        $banks = Models\Bank::all();
        $configs = Models\Config::all();
        $configs = $configs->pluck('value', 'key')->toArray();
        return view('configs.create', compact('banks', 'subjects', 'configs'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'co_name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'co_logo' => 'nullable|image|mimes:jpeg,jpg,png,svg|max:10240',
            'co_address' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'co_economical_code' => 'nullable|integer',
            'co_national_code' => 'nullable|integer',
            'co_postal_code' => 'nullable|integer',
            'co_phone_number' => 'nullable|numeric|regex:/^09\d{9}$/',
            'cust_subject' => 'nullable|exists:subjects,id|integer',
            'bank' => 'nullable|exists:banks,id|integer',
            'cash' => 'nullable|numeric',
            'buy_discount' => 'nullable|numeric',
            'sell_discount' => 'nullable|numeric',
            'sell_vat' => 'nullable|numeric',
            'buy_vat' => 'nullable|numeric',
            'sell_free' => 'nullable|numeric'
        ]);


        // Upload file (Storage or FTP)
        $file = $request->file('co_logo');
        if( $file ){
            $extension = $file->getClientOriginalExtension();
            $uniqueName = uniqid() . '.' . $extension;
            $co_logo = Models\Config::where('key', 'co_logo')->first();

            if( $co_logo ){
                $oldPath = 'public/'.$co_logo->value;
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
                    'type' => '',
                    'category' => ''
                ]);
            }
        }

        return redirect()->route('configs.index')->with('success', 'Config created successfully.');
    }

    public function destroy(Models\Config $config)
    {
        $config->delete();

        return redirect()->route('configs.index')->with('success', 'Config deleted successfully.');
    }
}
