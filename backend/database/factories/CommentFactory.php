<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => HelpRequest::factory(),
            'author_id' => User::factory(),
            'body' => 'Un commentaire utile sur la demande.',
            'edited_at' => null,
            'hidden_at' => null,
        ];
    }
}
