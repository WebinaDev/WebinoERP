<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\Public\PublicConsultationController;
use Modules\Marketing\Http\Controllers\Public\PublicContentController;
use Modules\Marketing\Http\Controllers\Public\PublicSiteController;

Route::get('/site', [PublicSiteController::class, 'site']);
Route::get('/home', [PublicSiteController::class, 'home']);
Route::get('/services', [PublicSiteController::class, 'services']);
Route::get('/solutions', [PublicSiteController::class, 'solutions']);

Route::get('/pages/{slug}', [PublicContentController::class, 'page']);

Route::get('/blog', [PublicContentController::class, 'blog']);
Route::get('/blog/{slug}', [PublicContentController::class, 'blogShow']);

Route::get('/magazine', [PublicContentController::class, 'magazine']);
Route::get('/magazine/{slug}', [PublicContentController::class, 'magazineShow']);

Route::get('/academy', [PublicContentController::class, 'academy']);
Route::get('/academy/{slug}', [PublicContentController::class, 'academyShow']);

Route::get('/portfolio', [PublicContentController::class, 'portfolio']);
Route::get('/portfolio/{slug}', [PublicContentController::class, 'portfolioShow']);

Route::get('/faq', [PublicContentController::class, 'faq']);
Route::get('/downloads', [PublicContentController::class, 'downloads']);
Route::get('/team', [PublicContentController::class, 'team']);
Route::get('/announcements', [PublicContentController::class, 'announcements']);
Route::get('/testimonials', [PublicContentController::class, 'testimonials']);

Route::get('/services/{slug}', [PublicContentController::class, 'serviceShow']);
Route::get('/solutions/{industry}', [PublicContentController::class, 'solutionIndustry']);
Route::get('/solutions/{industry}/{slug}', [PublicContentController::class, 'solutionPage']);

Route::post('/consultations', [PublicConsultationController::class, 'store']);
