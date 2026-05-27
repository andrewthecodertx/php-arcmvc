<?php

declare(strict_types=1);

namespace App\Controllers;

use Arc\Support\Controller;
use Arc\Http\Request;
use Arc\Http\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home.index', ['title' => 'Welcome to Arc']);
    }
}