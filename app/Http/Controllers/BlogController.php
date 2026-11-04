<?php

namespace App\Http\Controllers;

use App\Services\Blog\ListPublishedPostsService;
use App\Services\Blog\ShowPostService;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function index(ListPublishedPostsService $listPublishedPostsService): View
    {
        return view('blog.index', ['posts' => $listPublishedPostsService->run()]);
    }

    public function show(string $slug, ShowPostService $showPostService): View
    {
        return view('blog.show', ['post' => $showPostService->run($slug)]);
    }
}
