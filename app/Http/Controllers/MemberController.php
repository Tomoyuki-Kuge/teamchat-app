<?php

namespace App\Http\Controllers;

use App\Actions\Channels\SaveMember;
use App\Http\Requests\MemberRequest;
use App\Models\Channel;
use App\Models\User;
use App\Support\AuthInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Normalizer;
use Symfony\Component\HttpFoundation\Response;

/**
 * プライベートチャンネルのメンバー管理画面と所属変更を扱う。
 */
class MemberController extends Controller
{
    /**
     * メンバー一覧と追加候補の検索結果を表示する。
     */
    public function index(Request $request, Channel $channel): Response
    {
        $this->authorize('manageMembers', $channel);
        $raw = $request->query('q');
        $invalid = $raw !== null && ! is_string($raw);
        $q = is_string($raw) ? $raw : '';
        $feedback = $request->session()->get('member_form') === 'members.store:'.$channel->id
            ? $request->session()->get('errors')?->getBag('default') ?? new MessageBag : new MessageBag;
        $removeFeedback = $request->session()->get('member_form') === 'members.destroy:'.$channel->id
            ? $request->session()->get('errors')?->getBag('default') ?? new MessageBag : new MessageBag;
        if ($invalid) {
            $feedback = new MessageBag(['q' => '検索キーワードを正しい形式で入力してください']);
        }
        $members = $channel->members()
            ->orderByRaw('CASE WHEN users.id = ? THEN 0 ELSE 1 END', [$channel->creator_id])
            ->orderBy('channel_user.joined_order')->get();
        $searched = ! $invalid && AuthInput::trim($this->compare($q)) !== '';
        $candidates = collect();
        if ($searched) {
            $needle = $this->compare($q);
            $candidates = User::whereNotIn('id', $members->modelKeys())->orderBy('name')->orderBy('id')->get()
                ->filter(fn (User $user) => str_contains($this->compare($user->name), $needle)
                    || str_contains($this->compare($user->email), $needle));
        }
        $selected = $request->session()->get('member_form') === 'members.store:'.$channel->id
            ? $request->old('user_id') : null;

        return response()->view('members.index', compact('channel', 'members', 'candidates', 'q', 'searched', 'feedback', 'removeFeedback', 'selected'), $invalid ? 422 : 200);
    }

    /**
     * 選択した社員をメンバーに追加して一覧へ戻す。
     */
    public function store(MemberRequest $request, Channel $channel, SaveMember $action): RedirectResponse
    {
        $action->add($request->user(), $channel, (int) $request->validated('user_id'));

        return $this->back($channel, $request);
    }

    /**
     * 指定した社員の所属を解除して一覧へ戻す。
     */
    public function destroy(MemberRequest $request, Channel $channel, string $user, SaveMember $action): RedirectResponse
    {
        $action->remove($request->user(), $channel, (int) $user);

        return $this->back($channel, $request);
    }

    /**
     * 検索語を引き継いでメンバー管理画面へ戻す。
     */
    private function back(Channel $channel, Request $request): RedirectResponse
    {
        return redirect()->route('members.index', ['channel' => $channel, 'q' => $request->input('q')], 303);
    }

    /**
     * 検索比較用に文字の表記を揃える。
     */
    private function compare(string $value): string
    {
        return strtolower(Normalizer::normalize($value, Normalizer::FORM_KC));
    }
}
