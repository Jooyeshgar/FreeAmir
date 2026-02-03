<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Customer;
use App\Services\CommentService;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $service)
    {
        $this->middleware('permission:customers.view', ['only' => ['index']]);
        $this->middleware('permission:customers.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:customers.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:customers.delete', ['only' => ['destroy']]);
    }

    public function index(Customer $customer)
    {
        $comments = Comment::where('customer_id', $customer->id)->paginate(25);

        return view('customers.comments.index', compact('comments', 'customer'));
    }

    public function create(Customer $customer)
    {
        $comment = new Comment;

        return view('customers.comments.create', compact('customer', 'comment'));
    }

    public function store(StoreCommentRequest $request)
    {
        $validated = $request->validated();

        $comment = $this->service->create($validated);

        return redirect()->route('comments.index', $comment->customer)->with('success', __('Comment created successfully.'));
    }

    public function edit(Customer $customer, Comment $comment)
    {
        return view('customers.comments.edit', compact('comment'));
    }

    public function update(StoreCommentRequest $request, Customer $customer, Comment $comment)
    {
        $validated = $request->validated();
        $this->service->update($comment, $validated);

        return redirect()->route('comments.index', $customer)->with('success', __('Comment updated successfully.'));
    }

    public function destroy(Customer $customer, Comment $comment)
    {
        $this->service->delete($comment);

        return redirect()->route('comments.index', $customer)->with('success', __('Comment deleted successfully.'));
    }
}
