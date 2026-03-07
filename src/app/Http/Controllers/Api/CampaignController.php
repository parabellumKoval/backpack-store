<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Backpack\Store\app\Models\Campaign;
use Backpack\Store\app\Services\Campaign\CampaignPayloadService;
use Backpack\Store\app\Services\Campaign\CampaignResolverService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignResolverService $resolver,
        protected CampaignPayloadService $payload
    ) {
    }

    public function index(Request $request)
    {
        $placement = $request->query('placement');
        $country = strtolower((string) (\Store::context()->country ?: \Store::country()));

        $campaigns = $this->resolver->listActive($country, is_string($placement) ? $placement : null);

        return response()->json([
            'data' => $campaigns->map(fn(Campaign $campaign) => $this->payload->make($campaign))->values(),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $country = strtolower((string) (\Store::context()->country ?: \Store::country()));

        $campaign = Campaign::query()
            ->activeAt()
            ->activeForCountry($country)
            ->where('slug', $slug)
            ->whereExists(function ($query) use ($country) {
                $query->selectRaw('1')
                    ->from('ak_campaign_product as cp')
                    ->whereColumn('cp.campaign_id', 'ak_campaigns.id')
                    ->where('cp.country_code', $country);
            })
            ->firstOrFail();

        $productCount = DB::table('ak_campaign_product')
            ->where('campaign_id', $campaign->id)
            ->where('country_code', $country)
            ->count();

        $data = $this->payload->make($campaign);
        $data['products_count'] = (int) $productCount;

        return response()->json($data);
    }
}
