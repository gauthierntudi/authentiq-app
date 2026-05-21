<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ClientPageController extends Controller
{
    public function index(): View
    {
        return view('pages.clients');
    }
}
