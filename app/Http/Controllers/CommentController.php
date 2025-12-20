<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:comments.view', ['only' => ['index']]);
        $this->middleware('permission:comments.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:comments.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:comments.delete', ['only' => ['destroy']]);
    }

    private $rules = [
        'content' => 'nullable|string|max:500|required_without:rating',
        'rating' => 'nullable|numeric|min:0|max:5|required_without:content',
        'customer_id' => 'required|exists:customers,id',
        'user_id' => 'required|exists:users,id',
    ];

    public function index(\App\Models\Customer $customer)
    {
        $comments = Comment::where('customer_id', $customer->id)->paginate(25);

        return view('customers.comments.index', compact('comments', 'customer'));
    }

    public function create(\App\Models\Customer $customer)
    {
        $comment = new Comment;

        return view('customers.comments.create', compact('customer', 'comment'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules);
        Comment::create($validated);

        return redirect()->route('comments.index', $validated['customer_id'])->with('success', 'Comment created successfully.');
    }

    public function edit(Comment $comment)
    {
        return view('customers.comments.edit', compact('comment'));
    }

    public function update(Request $request, Comment $comment)
    {
        $validated = $request->validate($this->rules);
        $comment->update($validated);

        return redirect()->route('comments.index', $comment->customer_id)->with('success', 'Comment updated successfully.');
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();

        return redirect()->route('comments.index', $comment->customer_id)->with('success', 'Comment deleted successfully.');
    }
}
