<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortfolioRequest;
use App\Models\Portfolio;
use App\Services\PortfolioListDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioListDataService $portfolioListData) {}

    public function index(Request $request): Response
    {
        $data = $this->portfolioListData->buildForUser($request->user());

        return Inertia::render('Portfolios/Index', [
            'portfolios' => $data['portfolios'],
            'totals' => $data['totals'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Portfolios/Create');
    }

    public function store(PortfolioRequest $request): RedirectResponse
    {
        $request->user()->portfolios()->create($request->validated());

        return redirect()
            ->route('portfolios.index')
            ->with('success', 'ポートフォリオを作成しました。');
    }

    public function edit(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Portfolios/Edit', [
            'portfolio' => $portfolio->only(['id', 'name', 'description']),
        ]);
    }

    public function update(PortfolioRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->update($request->validated());

        return redirect()
            ->route('portfolios.index')
            ->with('success', 'ポートフォリオを更新しました。');
    }

    public function destroy(Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return redirect()
            ->route('portfolios.index')
            ->with('success', 'ポートフォリオを削除しました。');
    }
}
