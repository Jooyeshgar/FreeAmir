<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition()
    {
        return [
            'user_id' => User::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'content' => $this->faker->sentence,
            'rating' => $this->faker->randomFloat(2, 0, 5),
        ];
    }

    public function withCustomer(Customer $customer): static
    {
        return $this->state([
            'customer_id' => $customer->id,
        ]);
    }
}
