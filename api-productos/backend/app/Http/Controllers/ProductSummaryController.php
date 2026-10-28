<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductSummaryController extends Controller
{
    /**
     * Cifras exactas del inventario, calculadas en la base de datos (una sola consulta),
     * sin traer todos los productos al navegador.
     */
    public function __invoke()
    {
        $r = Product::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(stock), 0) as units')
            ->selectRaw('COALESCE(SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END), 0) as with_stock')
            ->first();

        $total = (int) $r->total;
        $conStock = (int) $r->with_stock;

        return response()->json([
            'total' => $total,
            'with_stock' => $conStock,
            'without_stock' => $total - $conStock,
            'units' => (int) $r->units,
        ]);
    }
}
