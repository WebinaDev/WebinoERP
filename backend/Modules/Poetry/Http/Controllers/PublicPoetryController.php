<?php

namespace Modules\Poetry\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Poetry\Entities\PoetryPoem;
use Modules\Poetry\Entities\PoetryTerm;
use Modules\Poetry\Services\PoetryPresenter;
use Modules\Poetry\Services\PoetryText;

class PublicPoetryController extends Controller
{
    public function terms(Request $request): JsonResponse
    {
        $q = PoetryTerm::query()->orderBy('kind')->orderBy('sort')->orderBy('id');
        if (in_array($request->query('kind'), PoetryTerm::KINDS, true)) {
            $q->where('kind', $request->query('kind'));
        }

        return response()->json(['data' => $q->get()->map(fn ($t) => PoetryPresenter::term($t))->all()]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = $this->filtered($request);
        $sort = $request->query('sort');
        $sort === 'popular' ? $q->orderByDesc('view_count') : $q->orderByDesc('published_at')->orderByDesc('id');
        $page = $q->paginate(min(48, max(1, (int) $request->query('per_page', 12))));

        return response()->json([
            'data' => collect($page->items())->map(fn ($p) => PoetryPresenter::card($p))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function popular(Request $request): JsonResponse
    {
        $items = PoetryPoem::query()->published()->with(['terms', 'occasion', 'meter'])->orderByDesc('view_count')->limit(min(24, max(1, (int) $request->query('limit', 8))))->get();

        return response()->json(['data' => $items->map(fn ($p) => PoetryPresenter::card($p))->all()]);
    }

    public function show(string $slug): JsonResponse
    {
        $poem = $this->find($slug);
        DB::table('poetry_poems')->where('id', $poem->id)->increment('view_count');
        $poem->view_count++;
        $data = PoetryPresenter::full($poem);
        $data['related'] = $this->related($poem)->map(fn ($p) => PoetryPresenter::card($p))->all();

        return response()->json(['data' => $data]);
    }

    public function text(string $slug)
    {
        $poem = $this->find($slug);
        $name = (Str::slug($poem->slug) ?: 'poem').'.txt';

        return response(PoetryText::plain($poem), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function figure(Request $request, string $slug): JsonResponse
    {
        $term = PoetryTerm::query()->where('kind', 'figure')->where('slug', $slug)->firstOrFail();
        $request->merge(['figure' => $slug]);
        $page = $this->filtered($request)->orderByDesc('published_at')->orderByDesc('id')->paginate(min(48, max(1, (int) $request->query('per_page', 12))));

        return response()->json([
            'data' => ['figure' => PoetryPresenter::term($term), 'poems' => collect($page->items())->map(fn ($p) => PoetryPresenter::card($p))->all()],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    private function filtered(Request $request)
    {
        $q = PoetryPoem::query()->published()->with(['terms', 'occasion', 'meter']);
        $text = trim((string) $request->query('q', ''));
        if ($text !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], PoetryText::normalize(mb_substr($text, 0, 120))).'%';
            $q->where('search_text', 'like', $needle);
        }
        if ($occ = $request->query('occasion')) {
            $q->whereHas('occasion', fn ($w) => $w->where('slug', $occ));
        }
        if ($meter = $request->query('meter')) {
            $q->whereHas('meter', fn ($w) => $w->where('slug', $meter));
        }
        foreach (['figure', 'style'] as $kind) {
            if ($slug = $request->query($kind)) {
                $q->whereHas('terms', fn ($w) => $w->where('kind', $kind)->where('slug', $slug));
            }
        }

        return $q;
    }

    private function find(string $slug): PoetryPoem
    {
        return PoetryPoem::query()->published()->with(['stanzas', 'terms', 'occasion', 'meter'])->where('slug', $slug)->firstOrFail();
    }

    private function related(PoetryPoem $poem)
    {
        $termIds = $poem->terms->pluck('id')->all();

        return PoetryPoem::query()->published()->with(['terms', 'occasion', 'meter'])->where('id', '!=', $poem->id)
            ->where(function ($w) use ($termIds, $poem) {
                $w->whereHas('terms', fn ($t) => $t->whereIn('poetry_terms.id', $termIds ?: [0]));
                if ($poem->occasion_id) {
                    $w->orWhere('occasion_id', $poem->occasion_id);
                }
            })
            ->orderByDesc('view_count')->limit(6)->get();
    }
}
