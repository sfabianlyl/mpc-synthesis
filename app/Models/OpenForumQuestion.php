<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $question
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OpenForumQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['question'];
}
