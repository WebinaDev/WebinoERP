<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\CoreChatChannel;
use Modules\Core\Entities\CoreChatChannelMember;
use Modules\Core\Entities\CoreChatMessage;
use Modules\Core\Entities\CoreChatReadReceipt;
use Modules\Core\Events\Chat\ChannelReadReceipt;
use Modules\Core\Events\Chat\MessageCreated;
use Modules\Core\Events\Chat\MessageDeleted;
use Modules\Core\Events\Chat\MessageUpdated;
use Modules\Core\Events\Chat\UserTyping;

class ChatController extends Controller
{
    public function channels(Request $request): JsonResponse
    {
        $user = $request->user();
        $channels = CoreChatChannel::query()
            ->accessibleForUser($user)
            ->with(['members.user:id,name', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $channels]);
    }

    public function storeChannel(Request $request): JsonResponse
    {
        $this->authorize('create', CoreChatChannel::class);

        $data = $request->validate([
            'name' => 'nullable|string|max:191',
            'type' => 'required|string|in:public,private,direct',
        ]);

        $channel = DB::transaction(function () use ($data, $request) {
            $ch = CoreChatChannel::query()->create([
                'name' => $data['name'] ?? null,
                'type' => $data['type'],
                'created_by' => $request->user()->id,
            ]);
            CoreChatChannelMember::query()->create([
                'channel_id' => $ch->id,
                'user_id' => $request->user()->id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            return $ch;
        });

        return response()->json(['data' => $channel->load('members')], 201);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $channel = CoreChatChannel::query()->findOrFail($id);
        $this->authorize('view', $channel);

        $messages = CoreChatMessage::query()
            ->where('channel_id', $channel->id)
            ->with('author:id,name')
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 50), 100));

        return response()->json(['data' => $messages]);
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:core_chat_messages,id',
            'channel_id' => 'required_without:id|nullable|integer|exists:core_chat_channels,id',
            'body' => 'required|string|max:65535',
            'reply_to' => 'nullable|integer|exists:core_chat_messages,id',
            'attachments' => 'nullable|array',
        ]);

        if (! empty($data['id'])) {
            $message = CoreChatMessage::query()->findOrFail($data['id']);
            $channel = $message->channel;
            $this->authorize('participate', $channel);
            if ((int) $message->user_id !== (int) $request->user()->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $message->update([
                'body' => $data['body'],
                'attachments' => array_key_exists('attachments', $data) ? $data['attachments'] : $message->attachments,
                'edited_at' => now(),
            ]);
            $message->refresh();
            broadcast(new MessageUpdated($message))->toOthers();

            return response()->json(['data' => $message->load('author:id,name')]);
        }

        $channel = CoreChatChannel::query()->findOrFail($data['channel_id']);
        $this->authorize('participate', $channel);

        if ($channel->type === 'public' && ! $channel->userIsMember($request->user())) {
            CoreChatChannelMember::query()->firstOrCreate(
                [
                    'channel_id' => $channel->id,
                    'user_id' => $request->user()->id,
                ],
                [
                    'role' => 'member',
                    'joined_at' => now(),
                ]
            );
        }

        if (! empty($data['reply_to'])) {
            $parent = CoreChatMessage::query()->findOrFail($data['reply_to']);
            if ((int) $parent->channel_id !== (int) $channel->id) {
                return response()->json(['message' => 'Invalid reply_to for this channel.'], 422);
            }
        }

        $message = CoreChatMessage::query()->create([
            'channel_id' => $channel->id,
            'user_id' => $request->user()->id,
            'reply_to' => $data['reply_to'] ?? null,
            'body' => $data['body'],
            'attachments' => $data['attachments'] ?? null,
        ]);

        broadcast(new MessageCreated($message))->toOthers();

        return response()->json(['data' => $message->load('author:id,name')], 201);
    }

    public function direct(Request $request, int $userId): JsonResponse
    {
        if ($userId === $request->user()->id) {
            return response()->json(['message' => 'Cannot open direct chat with yourself.'], 422);
        }

        User::query()->findOrFail($userId);

        $existing = CoreChatChannel::query()
            ->where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if ($existing) {
            return response()->json(['data' => $existing->load('members.user:id,name')]);
        }

        $channel = DB::transaction(function () use ($request, $userId) {
            $ch = CoreChatChannel::query()->create([
                'name' => null,
                'type' => 'direct',
                'created_by' => $request->user()->id,
            ]);
            foreach ([$request->user()->id, $userId] as $uid) {
                CoreChatChannelMember::query()->create([
                    'channel_id' => $ch->id,
                    'user_id' => $uid,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }

            return $ch;
        });

        return response()->json(['data' => $channel->load('members.user:id,name')], 201);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        $channel = CoreChatChannel::query()->findOrFail($id);
        $this->authorize('view', $channel);

        $data = $request->validate([
            'last_read_message_id' => 'nullable|integer|exists:core_chat_messages,id',
        ]);

        $lastId = $data['last_read_message_id'] ?? null;
        if ($lastId !== null) {
            $msg = CoreChatMessage::query()->findOrFail($lastId);
            if ((int) $msg->channel_id !== (int) $channel->id) {
                return response()->json(['message' => 'Message does not belong to this channel.'], 422);
            }
        }

        $receipt = CoreChatReadReceipt::query()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'user_id' => $request->user()->id,
            ],
            [
                'last_read_message_id' => $lastId,
                'read_at' => now(),
            ]
        );

        broadcast(new ChannelReadReceipt($channel->id, $request->user()->id, $lastId))->toOthers();

        return response()->json(['data' => $receipt]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $channels = CoreChatChannel::query()
            ->accessibleForUser($user)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $total = 0;
        foreach ($channels as $cid) {
            $total += $this->unreadForChannel($user, (int) $cid);
        }

        return response()->json(['data' => ['total' => $total]]);
    }

    protected function unreadForChannel(User $user, int $channelId): int
    {
        $receipt = CoreChatReadReceipt::query()
            ->where('channel_id', $channelId)
            ->where('user_id', $user->id)
            ->first();

        $lastReadId = $receipt?->last_read_message_id ?? 0;

        return (int) CoreChatMessage::query()
            ->where('channel_id', $channelId)
            ->where('id', '>', $lastReadId)
            ->where('user_id', '!=', $user->id)
            ->count();
    }

    public function searchMessages(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $messages = CoreChatMessage::query()
            ->where('body', 'like', $like)
            ->whereHas('channel', fn ($c) => $c->accessibleForUser($user))
            ->with(['channel:id,name,type', 'author:id,name'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function destroyMessage(Request $request, int $id): Response|JsonResponse
    {
        $message = CoreChatMessage::query()->findOrFail($id);
        $channel = $message->channel;
        $this->authorize('participate', $channel);

        if ((int) $message->user_id !== (int) $request->user()->id) {
            $member = CoreChatChannelMember::query()
                ->where('channel_id', $channel->id)
                ->where('user_id', $request->user()->id)
                ->first();
            if (! $member || $member->role !== 'admin') {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $message->delete();
        broadcast(new MessageDeleted($channel->id, $id, $request->user()->id))->toOthers();

        return response()->noContent();
    }

    public function typing(Request $request, int $id): Response
    {
        $channel = CoreChatChannel::query()->findOrFail($id);
        $this->authorize('participate', $channel);

        broadcast(new UserTyping($channel->id, $request->user()->id))->toOthers();

        return response()->noContent();
    }
}
