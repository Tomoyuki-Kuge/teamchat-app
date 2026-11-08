<?php

namespace App\Http\Controllers;

use App\Support\Search\MessageSearch;
use App\Support\Search\SearchText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * メッセージ検索画面を扱う。
 */
class SearchController extends Controller
{
    /**
     * 検索条件を検証し、閲覧可能なメッセージの検索結果を表示する。
     */
    public function index(Request $request, MessageSearch $search): Response
    {
        $input = $request->query();
        $q = is_string($input['q'] ?? null) ? $input['q'] : '';
        $words = SearchText::words($q);
        $integer = ['bail', 'required', 'integer', 'max:'.PHP_INT_MAX, 'regex:/^[0-9]+$/D'];
        $validator = Validator::make($input, [
            'q' => ['required', 'string'], 'sort' => ['sometimes', 'required', 'in:newest,oldest'],
            'page' => ['sometimes', ...$integer, 'min:1'],
            'until_id' => ['sometimes', ...$integer, 'min:0'],
        ], ['q.required' => '検索キーワードを入力してください',
            'q.string' => '検索キーワードを正しい形式で入力してください',
            '*' => '表示条件が正しくありません']);
        $validator->after(function ($validator) use ($words) {
            if ($words === [] && ! $validator->errors()->has('q')) {
                $validator->errors()->add('q', '検索キーワードを入力してください');
            }
        });
        $invalid = $validator->fails();
        $feedback = $validator->errors();
        $sort = is_string($input['sort'] ?? null) && in_array($input['sort'], ['newest', 'oldest'], true) ? $input['sort'] : 'newest';
        $data = ['results' => collect(), 'total' => 0, 'until' => 0, 'page' => 1, 'lastPage' => 1];
        if (! $invalid) {
            $data = $search->find($request->user(), $words, $sort, (int) ($input['page'] ?? 1), isset($input['until_id']) ? (int) $input['until_id'] : null);
            if ($data['page'] > $data['lastPage']) {
                return redirect()->route('search', ['q' => $q, 'sort' => $sort, 'page' => $data['lastPage'], 'until_id' => $data['until']]);
            }
        }

        return response()->view('search.index', $data + compact('q', 'words', 'sort', 'invalid', 'feedback'), $invalid ? 422 : 200);
    }
}
