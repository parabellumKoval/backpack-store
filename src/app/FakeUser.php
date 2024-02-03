<?php

namespace Backpack\Store\app;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\FakeUserFactory;

class FakeUser extends Authenticatable
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
    return FakeUserFactory::new();
  }

  public function toOrderArray() {
    return [
      'firstname' => 'First Name',
      'lastname' => 'Last Name',
      'email' => $this->email,
      'phone' => 'Phone'
    ];      
  }
}
