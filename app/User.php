<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table    = 'users';
    protected $fillable = [
        'name', 'email', 'access_token', 'is_active',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token',
    ];
}
