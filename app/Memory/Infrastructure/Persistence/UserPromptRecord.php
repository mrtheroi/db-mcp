<?php

namespace App\Memory\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'session_id', 'project', 'content'])]
class UserPromptRecord extends Model
{
    protected $table = 'user_prompts';
}
