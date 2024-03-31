<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index()
    {
        $banks = Models\Bank::paginate(12);
        $cols = [
            'name'
        ];
        return view('banks.index', compact('banks', 'cols'));
    }

    public function create()
    {
        $fields = $this->fields();
        return view('banks.create', compact('fields'));
    }

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'name'=>'required|max:20',
        ]);

        Models\Bank::create($validatedData);

        return redirect()->route('banks.index')->with('success', 'Bank created successfully.');
    }

    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit(Models\Bank $bank)
    {
        $fields = $this->fields();
        return view('banks.edit', compact('bank', 'fields'));
    }

    public function update(Request $request, Models\Bank $bank)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'name'=>'required|max:20',
        ]);

        $bank->update($validatedData);

        return redirect()->route('banks.index')->with('success', 'Bank updated successfully.');
    }

    public function destroy(Models\Bank $bank)
    {
        $bank->delete();

        return redirect()->route('banks.index')->with('success', 'Bank deleted successfully.');
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'نام بانک', 'type' => 'text'],
        ];
    }
}
