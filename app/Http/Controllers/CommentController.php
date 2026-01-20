<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Services\CommentService;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $service)
    {
        $this->middleware('permission:comments.view', ['only' => ['index']]);
        $this->middleware('permission:comments.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:comments.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:comments.delete', ['only' => ['destroy']]);
    }

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

    public function store(StoreCommentRequest $request)
    {
        $validated = $request->validated();

        $this->service->create($validated);

        return redirect()->route('comments.index', $validated['customer_id'])->with('success', __('Comment created successfully.'));
    }

    public function edit(Comment $comment)
    {
        return view('customers.comments.edit', compact('comment'));
    }

    public function update(StoreCommentRequest $request, Comment $comment)
    {
        $validated = $request->validated();
        $this->service->update($comment, $validated);

        return redirect()->route('comments.index', $comment->customer_id)->with('success', __('Comment updated successfully.'));
    }

    public function destroy(Comment $comment)
    {
        $this->service->delete($comment);

        return redirect()->route('comments.index', $comment->customer_id)->with('success', __('Comment deleted successfully.'));
    }
}
