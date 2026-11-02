<?php

namespace Database\Factories;

use App\Models\ConditionReport;
use App\Models\ConditionSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConditionReport>
 */
class ConditionReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'condition_session_id' => ConditionSession::factory(), 'user_id' => User::factory(),
            'role' => 'owner', 'kind' => 'initial', 'function_state' => 'working', 'photos' => [], 'submitted_at' => now(),
        ];
    }
}
