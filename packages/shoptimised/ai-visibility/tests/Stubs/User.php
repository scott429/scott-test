<?php

namespace Shoptimised\AiVisibility\Tests\Stubs;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Shoptimised\AiVisibility\Concerns\InteractsWithAiVisibility;
use Spatie\Permission\Traits\HasRoles;

/**
 * Stand-in for the host application's User during package tests. Mirrors how a
 * real Shoptimised User would adopt the package: HasRoles + InteractsWithAiVisibility.
 */
class User extends Authenticatable
{
    use HasRoles;
    use InteractsWithAiVisibility;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}
