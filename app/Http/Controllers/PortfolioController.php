<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index()
    {
        $portfolios = Portfolio::latest()->get();
        return view('portfolios.index', compact('portfolios'));
    }

    public function create()
    {
        return view('portfolios.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'coin_name' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'buy_price' => 'required|numeric|min:0',
            'current_price' => 'required|numeric|min:0',
        ]);

        Portfolio::create($validated);

        return redirect()->route('portfolios.index')->with('success', '登録しました。');
    }

    public function show(Portfolio $portfolio)
    {
        return view('portfolios.show', compact('portfolio'));
    }

    public function edit(Portfolio $portfolio)
    {
        return view('portfolios.edit', compact('portfolio'));
    }

    public function update(Request $request, Portfolio $portfolio)
    {
        $validated = $request->validate([
            'coin_name' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'buy_price' => 'required|numeric|min:0',
            'current_price' => 'required|numeric|min:0',
        ]);

        $portfolio->update($validated);

        return redirect()->route('portfolios.index')->with('success', '更新しました。');
    }

    public function destroy(Portfolio $portfolio)
    {
        $portfolio->delete();

        return redirect()->route('portfolios.index')->with('success', '削除しました。');
    }
}