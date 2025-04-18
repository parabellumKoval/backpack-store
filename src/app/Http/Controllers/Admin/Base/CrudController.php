<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Base;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Backpack\CRUD\app\Http\Controllers\CrudController as BackpackCrudController;

class CrudController extends BackpackCrudController
{
    /**
     * Handle select2_multiple field updates via AJAX
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleSelect2MultipleRouter($id)
    {
        $field = request('field');
        $values = request('values');
        
        if (!$field) {
            return response()->json(['error' => trans('backpack-store::admin.errors.field_not_provided')], 400);
        }

        $entry = $this->crud->getEntry($id);
        
        if (!$entry) {
            return response()->json(['error' => trans('backpack-store::admin.errors.entry_not_found')], 404);
        }

        try {
            $entry->{$field}()->sync($values);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}