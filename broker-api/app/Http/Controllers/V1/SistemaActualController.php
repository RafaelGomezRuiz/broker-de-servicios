<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Sistema;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Sistema', weight: 0)]
class SistemaActualController extends Controller
{
    /**
     * Comprobar la llave
     *
     * Devuelve el sistema dueño de la llave usada. Sirve para comprobar que la llave
     * funciona (por ejemplo, desde Postman) antes de enviar correos.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Sistema $sistema */
        $sistema = $request->user();

        return response()->json([
            'codigo' => $sistema->codigo,
            'nombre' => $sistema->nombre,
            'llave' => $sistema->currentAccessToken()->name,
        ]);
    }
}
