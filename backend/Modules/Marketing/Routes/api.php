<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\MarketingAcademyChaptersController;
use Modules\Marketing\Http\Controllers\MarketingAcademyController;
use Modules\Marketing\Http\Controllers\MarketingAcademyLessonsController;
use Modules\Marketing\Http\Controllers\MarketingAcademyStructureController;
use Modules\Marketing\Http\Controllers\MarketingAnnouncementsController;
use Modules\Marketing\Http\Controllers\MarketingBlogCategoriesController;
use Modules\Marketing\Http\Controllers\MarketingBlogController;
use Modules\Marketing\Http\Controllers\MarketingDownloadsController;
use Modules\Marketing\Http\Controllers\MarketingFaqController;
use Modules\Marketing\Http\Controllers\MarketingFormsController;
use Modules\Marketing\Http\Controllers\MarketingMagazineController;
use Modules\Marketing\Http\Controllers\MarketingMediaController;
use Modules\Marketing\Http\Controllers\MarketingMenusController;
use Modules\Marketing\Http\Controllers\MarketingPagesController;
use Modules\Marketing\Http\Controllers\MarketingPortfolioCategoriesController;
use Modules\Marketing\Http\Controllers\MarketingPortfolioController;
use Modules\Marketing\Http\Controllers\MarketingServiceCategoriesController;
use Modules\Marketing\Http\Controllers\MarketingServicesController;
use Modules\Marketing\Http\Controllers\MarketingSettingsController;
use Modules\Marketing\Http\Controllers\MarketingSolutionsController;
use Modules\Marketing\Http\Controllers\MarketingTeamController;
use Modules\Marketing\Http\Controllers\MarketingTestimonialsController;

Route::get('/settings', [MarketingSettingsController::class, 'show']);
Route::get('/seo-routes', [\Modules\Marketing\Http\Controllers\MarketingSeoController::class, 'index']);
Route::put('/seo-routes', [\Modules\Marketing\Http\Controllers\MarketingSeoController::class, 'update']);
Route::put('/seo-routes/{id}', [\Modules\Marketing\Http\Controllers\MarketingSeoController::class, 'update'])->whereNumber('id');
Route::put('/pages/{id}/seo', function (\Illuminate\Http\Request $request, int $id) {
    $row = \Modules\Marketing\Entities\MarketingPage::query()->findOrFail($id);
    $data = $request->validate(['seo' => 'present|array', 'seo.title' => 'nullable|string|max:120', 'seo.description' => 'nullable|string|max:320', 'seo.og_image' => 'nullable|string|max:500', 'seo.canonical' => 'nullable|string|max:500', 'seo.noindex' => 'nullable|boolean']);
    $row->meta = \Modules\Marketing\Http\Controllers\Builder\BuilderController::withSeo($row->meta, $data['seo']);
    $row->save();

    return response()->json(['data' => ['id' => $row->id, 'seo' => $row->meta['seo']]]);
})->whereNumber('id');
Route::put('/settings', [MarketingSettingsController::class, 'update']);

Route::apiResource('pages', MarketingPagesController::class);

Route::get('/blog/categories', [MarketingBlogCategoriesController::class, 'index']);
Route::post('/blog/categories', [MarketingBlogCategoriesController::class, 'store']);
Route::get('/blog/categories/{id}', [MarketingBlogCategoriesController::class, 'show']);
Route::put('/blog/categories/{id}', [MarketingBlogCategoriesController::class, 'update']);
Route::delete('/blog/categories/{id}', [MarketingBlogCategoriesController::class, 'destroy']);

Route::apiResource('blog', MarketingBlogController::class);

Route::apiResource('magazine', MarketingMagazineController::class);
Route::get('academy/{id}/structure', [MarketingAcademyStructureController::class, 'show'])->whereNumber('id');
Route::put('academy/{id}/structure', [MarketingAcademyStructureController::class, 'save'])->whereNumber('id');
Route::apiResource('academy-chapters', MarketingAcademyChaptersController::class);
Route::apiResource('academy-lessons', MarketingAcademyLessonsController::class);
Route::apiResource('academy', MarketingAcademyController::class);
Route::apiResource('portfolio-categories', MarketingPortfolioCategoriesController::class);
Route::apiResource('portfolio', MarketingPortfolioController::class);

Route::get('/menus', [MarketingMenusController::class, 'index']);
Route::post('/menus', [MarketingMenusController::class, 'store']);
Route::put('/menus/{id}', [MarketingMenusController::class, 'update']);
Route::delete('/menus/{id}', [MarketingMenusController::class, 'destroy']);
Route::post('/menus/{id}/items', [MarketingMenusController::class, 'storeItem']);
Route::put('/menu-items/{itemId}', [MarketingMenusController::class, 'updateItem']);
Route::delete('/menu-items/{itemId}', [MarketingMenusController::class, 'destroyItem']);

Route::get('/forms/uploads/{token}', [MarketingFormsController::class, 'upload'])->where('token', '[A-Za-z0-9]{40}');
Route::apiResource('forms', MarketingFormsController::class);
Route::get('/forms/{id}/submissions', [MarketingFormsController::class, 'submissions']);
Route::apiResource('faq', MarketingFaqController::class);
Route::apiResource('team', MarketingTeamController::class);
Route::apiResource('announcements', MarketingAnnouncementsController::class);
Route::apiResource('testimonials', MarketingTestimonialsController::class);
Route::apiResource('downloads', MarketingDownloadsController::class);

Route::get('/service-categories', [MarketingServiceCategoriesController::class, 'index']);
Route::post('/service-categories', [MarketingServiceCategoriesController::class, 'store']);
Route::get('/service-categories/{id}', [MarketingServiceCategoriesController::class, 'show']);
Route::put('/service-categories/{id}', [MarketingServiceCategoriesController::class, 'update']);
Route::delete('/service-categories/{id}', [MarketingServiceCategoriesController::class, 'destroy']);

Route::apiResource('services', MarketingServicesController::class);

Route::get('/solutions/industries', [MarketingSolutionsController::class, 'industries']);
Route::post('/solutions/industries', [MarketingSolutionsController::class, 'storeIndustry']);
Route::put('/solutions/industries/{id}', [MarketingSolutionsController::class, 'updateIndustry']);
Route::delete('/solutions/industries/{id}', [MarketingSolutionsController::class, 'destroyIndustry']);
Route::get('/solutions/industries/{industryId}/pages', [MarketingSolutionsController::class, 'pages']);
Route::post('/solutions/industries/{industryId}/pages', [MarketingSolutionsController::class, 'storePage']);
Route::put('/solutions/pages/{id}', [MarketingSolutionsController::class, 'updatePage']);
Route::delete('/solutions/pages/{id}', [MarketingSolutionsController::class, 'destroyPage']);

Route::get('/media', [MarketingMediaController::class, 'index']);
Route::get('/media/folders', [MarketingMediaController::class, 'folders']);
Route::post('/media/folders', [MarketingMediaController::class, 'storeFolder']);
Route::post('/media/upload', [MarketingMediaController::class, 'upload']);
Route::delete('/media/{id}', [MarketingMediaController::class, 'destroy']);

Route::get('/popups', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'index']);
Route::post('/popups', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'store']);
Route::get('/popups/leads', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'allLeads']);
Route::get('/popups/{popup}', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'show'])->whereNumber('popup');
Route::put('/popups/{popup}', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'update'])->whereNumber('popup');
Route::delete('/popups/{popup}', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'destroy'])->whereNumber('popup');
Route::get('/popups/{popup}/leads', [\Modules\Marketing\Http\Controllers\MarketingPopupController::class, 'leads'])->whereNumber('popup');
