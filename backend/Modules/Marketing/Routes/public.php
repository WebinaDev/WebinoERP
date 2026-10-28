<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\Builder\BuilderGlobalsController;
use Modules\Marketing\Http\Controllers\Builder\PublicBuilderController;
use Modules\Marketing\Http\Controllers\Public\PublicConsultationController;
use Modules\Marketing\Http\Controllers\Public\PublicContentController;
use Modules\Marketing\Http\Controllers\Public\PublicFormsController;
use Modules\Marketing\Http\Controllers\Public\PublicSiteController;

Route::middleware('throttle:marketing-public')->group(function () {
    Route::get('/site', [PublicSiteController::class, 'site'])->middleware('public.cache');
    Route::get('/sitemap', [\Modules\Marketing\Http\Controllers\Public\PublicSitemapController::class, 'index']);
    Route::get('/home', [PublicSiteController::class, 'home']);
    Route::get('/services', [PublicSiteController::class, 'services']);
    Route::get('/solutions', [PublicSiteController::class, 'solutions']);

    Route::get('/pages/{slug}', [PublicContentController::class, 'page']);

    Route::get('/blog', [PublicContentController::class, 'blog']);
    Route::get('/blog-categories', [PublicContentController::class, 'blogCategories']);
    Route::get('/blog/{slug}', [PublicContentController::class, 'blogShow']);

    Route::get('/magazine', [PublicContentController::class, 'magazine']);
    Route::get('/magazine/{slug}', [PublicContentController::class, 'magazineShow']);

    Route::get('/academy', [PublicContentController::class, 'academy']);
    Route::get('/academy/{slug}', [PublicContentController::class, 'academyShow']);

    Route::get('/builder/pages/{slug}', [PublicBuilderController::class, 'page'])->middleware('public.cache');
    Route::get('/builder/preview/{token}', [PublicBuilderController::class, 'preview'])->middleware('throttle:60,1');
    Route::post('/builder/ab/{test}/event', [\Modules\Marketing\Http\Controllers\Builder\BuilderAbController::class, 'event'])->whereNumber('test')->middleware('throttle:120,1');
    Route::get('/builder/collections/{slug}/items', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'publicItems']);
    Route::get('/builder/collections/{slug}/items/{collectionItem}', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'publicItem']);
    Route::get('/builder/templates/{kind}', [PublicBuilderController::class, 'template'])->middleware('public.cache');
    Route::get('/builder/resolve', [PublicBuilderController::class, 'resolve'])->middleware('public.cache');
    Route::get('/builder/globals', [BuilderGlobalsController::class, 'publicShow'])->middleware('public.cache');
    Route::get('/menus/{location}', [PublicFormsController::class, 'menu'])->middleware('public.cache');
    Route::get('/forms/{slug}', [PublicFormsController::class, 'show']);

    Route::get('/portfolio', [PublicContentController::class, 'portfolio']);
    Route::get('/portfolio-categories', [PublicContentController::class, 'portfolioCategories']);
    Route::get('/portfolio/{slug}', [PublicContentController::class, 'portfolioShow']);

    Route::get('/faq', [PublicContentController::class, 'faq']);
    Route::get('/downloads', [PublicContentController::class, 'downloads']);
    Route::get('/team', [PublicContentController::class, 'team']);
    Route::get('/announcements', [PublicContentController::class, 'announcements']);
    Route::get('/announcements/{slug}', [PublicContentController::class, 'announcementShow']);
    Route::get('/testimonials', [PublicContentController::class, 'testimonials']);

    Route::get('/services/{slug}', [PublicContentController::class, 'serviceShow']);
    Route::get('/solutions/{industry}', [PublicContentController::class, 'solutionIndustry']);
    Route::get('/solutions/{industry}/{slug}', [PublicContentController::class, 'solutionPage']);
});

Route::post('/consultations', [PublicConsultationController::class, 'store'])
    ->middleware('throttle:5,1');

Route::post('/forms/{slug}', [PublicFormsController::class, 'submit'])
    ->middleware('throttle:5,1');

Route::post('/downloads/{id}/request', [PublicContentController::class, 'downloadRequest'])
    ->whereNumber('id')->middleware('throttle:10,1');
Route::post('/forms/{slug}/upload', [PublicFormsController::class, 'upload'])
    ->middleware('throttle:10,1');

Route::middleware('throttle:marketing-public')->group(function () {
    Route::get('/popups', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'publicIndex']);
});
Route::post('/popups/{popup}/event', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'event'])
    ->whereNumber('popup')->middleware('throttle:30,1');
