<?php

namespace App\Models;

use Database\Factories\ConditionReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionReport extends Model
{
    /** @use HasFactory<ConditionReportFactory> */
    use HasFactory;

    protected $fillable = ['condition_session_id', 'user_id', 'role', 'kind', 'initial_key', 'function_state', 'accessories', 'serial_number', 'condition_note', 'photos', 'submitted_at', 'decision', 'decision_note', 'decided_at'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConditionSession::class, 'condition_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['photos' => 'array', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
