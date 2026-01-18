<?php

namespace App\Services;

use App\Models\Comment;
use DB;

class CommentService
{
    public function __construct() {}

    public function create(array $data): Comment
    {
        $data['company_id'] ??= getActiveCompany();

        $comment = Comment::create($data);

        return $comment;
    }

    public function update(Comment $comment, array $data): Comment
    {
        $comment->fill($data);
        $comment->save();

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $comment->delete();
        });
    }
}
