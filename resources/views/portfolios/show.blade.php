@extends('layouts.app')

@section('content')
    <h2>詳細</h2>

    <table>
        <tr>
            <th>コイン名</th>
            <td>{{ $portfolio->coin_name }}</td>
        </tr>
        <tr>
            <th>数量</th>
            <td>{{ $portfolio->amount }}</td>
        </tr>
        <tr>
            <th>取得価格</th>
            <td>{{ $portfolio->buy_price }}</td>
        </tr>
        <tr>
            <th>現在価格</th>
            <td>{{ $portfolio->current_price }}</td>
        </tr>
        <tr>
            <th>評価額</th>
            <td>{{ $portfolio->valuation }}</td>
        </tr>
        <tr>
            <th>損益</th>
            <td>{{ $portfolio->profit }}</td>
        </tr>
    </table>

    <a href="{{ route('portfolios.index') }}" class="btn">戻る</a>
@endsection