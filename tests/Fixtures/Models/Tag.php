<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Intentionally has NO factory — used to prove record-bound pages are skipped
 * with a visible reason rather than failing.
 */
class Tag extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
