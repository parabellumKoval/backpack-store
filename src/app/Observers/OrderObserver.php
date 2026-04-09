<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Models\Order;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Events\OrderСompleted;
use Backpack\Store\app\Events\OrderRejected;
use Backpack\Store\app\Events\OrderDeleted;

class OrderObserver
{
  public function created(Order $order): void
  {
      $this->queueLoyaltyDiscountSync($order);
  }

  public function updated(Order $order): void
  {
      if ($order->wasChanged('status')) {
          if ($order->status === 'completed') {
              event(new OrderСompleted($order));
          } else {
              event(new OrderRejected($order));
          }
      }

      if ($this->shouldSyncLoyaltyDiscount($order)) {
          $this->queueLoyaltyDiscountSync($order, true);
      }
  }

  public function deleting(Order $order) {
      event(new OrderDeleted($order));
  }

  public function deleted(Order $order): void
  {
      $this->queueLoyaltyDiscountSync($order, true);
  }

  protected function shouldSyncLoyaltyDiscount(Order $order): bool
  {
      foreach ([
          'status',
          'grand_total',
          'price',
          'shipping_total',
          'tax_total',
          'currency_code',
          'fx_rate',
          'orderable_id',
          'orderable_type',
      ] as $field) {
          if ($order->wasChanged($field)) {
              return true;
          }
      }

      return false;
  }

  protected function queueLoyaltyDiscountSync(Order $order, bool $includeOriginal = false): void
  {
      $serviceClass = \Backpack\Profile\app\Services\LoyaltyDiscountService::class;
      if (!class_exists($serviceClass)) {
          return;
      }

      $userIds = $this->resolveLoyaltyUserIds($order);
      if ($includeOriginal) {
          $userIds = array_merge($userIds, $this->resolveLoyaltyUserIds($order, true));
      }

      $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
      if ($userIds === []) {
          return;
      }

      DB::afterCommit(function () use ($serviceClass, $userIds): void {
          try {
              $service = app($serviceClass);
              foreach ($userIds as $userId) {
                  $service->recalculateForUserId($userId);
              }
          } catch (\Throwable $exception) {
              \Log::warning('Failed to sync loyalty discount after order change.', [
                  'user_ids' => $userIds,
                  'error' => $exception->getMessage(),
              ]);
          }
      });
  }

  /**
   * @return array<int, int>
   */
  protected function resolveLoyaltyUserIds(Order $order, bool $useOriginal = false): array
  {
      $profileModel = (string) config(
          'backpack.profile.profile_model',
          config('profile.profile_model', \Backpack\Profile\app\Models\Profile::class)
      );

      $userModelCandidates = array_values(array_unique(array_filter([
          config('dress.store.user_model'),
          config('backpack.profile.user_model'),
          config('profile.user_model'),
          \App\Models\User::class,
      ])));

      $orderableType = $useOriginal
          ? ($order->getOriginal('orderable_type') ?: $order->orderable_type)
          : $order->orderable_type;
      $orderableId = (int) ($useOriginal
          ? ($order->getOriginal('orderable_id') ?: $order->orderable_id)
          : $order->orderable_id);

      if ($orderableId <= 0) {
          return [];
      }

      if (in_array($orderableType, $userModelCandidates, true)) {
          return [$orderableId];
      }

      if ($orderableType === $profileModel) {
          $userId = $profileModel::query()
              ->whereKey($orderableId)
              ->value('user_id');

          return $userId ? [(int) $userId] : [];
      }

      if (!$useOriginal) {
          $order->loadMissing('orderable');
          $orderable = $order->orderable;

          if ($orderable instanceof \App\Models\User) {
              return [(int) $orderable->getKey()];
          }

          if (!empty($orderable->user_id)) {
              return [(int) $orderable->user_id];
          }
      }

      return [];
  }
}
