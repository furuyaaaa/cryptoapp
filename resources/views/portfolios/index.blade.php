@extends('layouts.app')

@section('content')
    <a href="{{ route('portfolios.create') }}" class="btn">新規追加</a>

    <table>
        <thead>
            <tr>
                <th>コイン名</th>
                <th>数量</th>
                <th>取得価格</th>
                <th>現在価格</th>
                <th>評価額</th>
                <th>損益</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($portfolios as $portfolio)
                <tr>
                    <td>{{ $portfolio->coin_name }}</td>
                    <td>{{ $portfolio->amount }}</td>
                    <td>{{ $portfolio->buy_price }}</td>
                    <td>{{ $portfolio->current_price }}</td>
                    <td>{{ $portfolio->valuation }}</td>
                    <td>{{ $portfolio->profit }}</td>
                    <td>
                        <a href="{{ route('portfolios.show', $portfolio) }}" class="btn">詳細</a>
                        <a href="{{ route('portfolios.edit', $portfolio) }}" class="btn">編集</a>

                        <form action="{{ route('portfolios.destroy', $portfolio) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">データがありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection