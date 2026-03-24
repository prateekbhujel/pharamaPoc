<?php

namespace App\Modules\Docs\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SwaggerController extends Controller
{
    public function __invoke(): View
    {
        return view('docs::index', [
            'specUrl' => asset('docs/openapi-v1.json'),
        ]);
    }
}
