<?php

namespace Modules\Accounting\Http\Controllers;

use App\Support\AppliesIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Entities\AccPerson;

class PersonController extends Controller
{
    use AppliesIndexQuery;

    public function index(Request $request): JsonResponse
    {
        $query = AccPerson::query();
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            [],
            ['name', 'mobile'],
            ['id', 'name', 'created_at'],
        );

        return $this->paginatedJsonResponse($paginator);
    }
}
