@extends('layouts.app')

@section('content')
    <h2>新規登録</h2>

    <form action="{{ route('portfolios.store') }}" method="POST">
        @csrf

        <label>コイン名</label>
        <input type="text" name="coin_name" value="{{ old('coin_name') }}">

        <label>数量</label>
        <input type="number" step="0.00000001" name="amount" value="{{ old('amount') }}">

        <label>取得価格</label>
        <input type="number" step="0.01" name="buy_price" value="{{ old('buy_price') }}">

        <label>現在価格</label>
        <input type="number" step="0.01" name="current_price" value="{{ old('current_price') }}">

        <button type="submit" class="btn">登録</button>
        <a href="{{ route('portfolios.index') }}" class="btn">戻る</a>
    </form>
@endsection