<?php

namespace App\Services;

use App\Models\Comment;
use DB;

class CommentService
{
    public function __construct(private readonly FiscalYearMutationGuard $fiscalYearMutationGuard) {}

    public function create(array $data): Comment
    {
        $this->fiscalYearMutationGuard->ensureOpen();
        $comment = Comment::create($data);

        return $comment;
    }

    public function update(Comment $comment, array $data): Comment
    {
        $this->fiscalYearMutationGuard->ensureOpen();
        $comment->fill($data);
        $comment->save();

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $this->fiscalYearMutationGuard->ensureOpen();
            $comment->delete();
        });
    }
}
