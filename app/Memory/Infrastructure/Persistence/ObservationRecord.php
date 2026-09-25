<?php

namespace App\Memory\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'session_id', 'type', 'title', 'content', 'project', 'scope', 'topic_key'])]
class ObservationRecord extends Model
{
    protected $table = 'observations';
}
