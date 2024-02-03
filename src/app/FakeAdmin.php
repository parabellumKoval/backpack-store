<?php

namespace Backpack\Store\app;

use Illuminate\Foundation\Auth\User;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\FakeAdminFactory;

class FakeAdmin extends User
{
  use HasFactory;
  protected $table = 'users';


  /**
   * Create a new factory instance for the model.
   *
   * @return \Illuminate\Database\Eloquent\Factories\Factory
   */
  protected static function newFactory()
  {
    return FakeAdminFactory::new();
  }
}