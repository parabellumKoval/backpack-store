<?php
namespace Backpack\Store\app\Contracts;

interface FilterService {
  public function getFiltersData(): array;
  public function getFiltersCount(): array;
}
