<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Customer;
use DB;

class CommentService
{
    public function __construct() {}

    public function create(array $data): Comment
    {
        app(FiscalYearMutationGuard::class)->ensureOpen(Customer::withoutGlobalScopes()->findOrFail($data['customer_id']));

        $comment = Comment::create($data);

        return $comment;
    }

    public function update(Comment $comment, array $data): Comment
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($comment);

        if (isset($data['customer_id'])) {
            app(FiscalYearMutationGuard::class)->ensureOpen(Customer::withoutGlobalScopes()->findOrFail($data['customer_id']));
        }

        $comment->fill($data);
        $comment->save();

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($comment);

        DB::transaction(function () use ($comment) {
            $comment->delete();
        });
    }
}
