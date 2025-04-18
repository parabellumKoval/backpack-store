@if($sps->count())
    <b>{{ trans('backpack-store::suppliers.suppliers') }}</b>
    <table>
        <tr>
            <th>{{ trans('backpack-store::suppliers.columns.name') }}</th>
            <th>{{ trans('backpack-store::suppliers.columns.article') }}</th>
            <th>{{ trans('backpack-store::suppliers.columns.code_barcode') }}</th>
            <th>{{ trans('backpack-store::suppliers.columns.in_stock') }}</th>
            <th>{{ trans('backpack-store::suppliers.columns.price') }}</th>
            <th>{{ trans('backpack-store::suppliers.columns.old_price') }}</th>
            <th>{{ trans('backpack-store::suppliers.columns.last_update') }}</th>
        </tr>
        @foreach($sps as $sp)
            <tr>
                <td><b style="color: {{ $sp->supplier->color ?? '#000' }}">{{ $sp->supplier->name ?? '-' }}</b></td>
                <td>{{ $sp->code }}</td>
                <td>{{ $sp->barcode }}</td>
                <td>{{ $sp->in_stock }}</td>
                <td>{{ !is_null($sp->price) ? $sp->price . $currency : '' }}</td>
                <td>{{ !is_null($sp->old_price) ? $sp->old_price . $currency : '' }}</td>
                <td>{{ $sp->updated_at }}</td>
            </tr>
        @endforeach
    </table>
@else
    {{ trans('backpack-store::suppliers.no_suppliers') }}
@endif