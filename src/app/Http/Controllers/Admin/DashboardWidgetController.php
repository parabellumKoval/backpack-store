<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Services\AdminDashboardWidgetService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardWidgetController extends Controller
{
    public function orders(Request $request, AdminDashboardWidgetService $service)
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:5'],
        ]);

        $country = $validated['country'] ?? null;
        $country = strtolower((string) $country);
        if ($country === '' || $country === 'all') {
            $country = null;
        }

        $payload = $service->ordersWidgetPayload($country);

        return response()->json([
            'orders' => $payload['orders'],
            'active' => $payload['active'],
        ]);
    }
}
