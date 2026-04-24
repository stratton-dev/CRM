<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmEmail;
use App\Models\User;
use Illuminate\Http\Request;

class CrmEmailsController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmEmail::query();

        if ($ownerId = $this->resolveUserId($request->input('owner_id'))) {
            $q->where('owner_user_id', $ownerId);
        }
        if ($folder = $request->string('folder')->toString()) {
            $q->where('folder', $folder);
        }
        if ($email = $request->string('email')->toString()) {
            $q->where(function ($w) use ($email) {
                $w->where('from_email', $email)->orWhere('to_email', $email);
            });
        }

        return $q->latest('sent_at')->paginate($request->integer('per_page', 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sender_id' => 'required',
            'from_name' => 'required|string|max:255',
            'from_email' => 'required|email|max:255',
            'to_email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'sent_at' => 'nullable|date',
        ]);

        $senderId = $this->resolveUserId($data['sender_id']);
        if (!$senderId) {
            return response()->json(['message' => 'Sender user not found.'], 422);
        }

        $sentAt = $data['sent_at'] ?? now();

        $sentEmail = CrmEmail::create([
            'owner_user_id' => $senderId,
            'from_name' => $data['from_name'],
            'from_email' => $data['from_email'],
            'to_email' => $data['to_email'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'folder' => 'SENT',
            'sent_at' => $sentAt,
            'read_at' => $sentAt,
        ]);

        $recipient = User::query()
            ->where('email', $data['to_email'])
            ->first();

        $inboxEmail = null;
        if ($recipient) {
            $inboxEmail = CrmEmail::create([
                'owner_user_id' => $recipient->id,
                'from_name' => $data['from_name'],
                'from_email' => $data['from_email'],
                'to_email' => $data['to_email'],
                'subject' => $data['subject'],
                'body' => $data['body'],
                'folder' => 'INBOX',
                'sent_at' => $sentAt,
            ]);
        }

        return response()->json([
            'sent' => $sentEmail,
            'inbox' => $inboxEmail,
        ], 201);
    }

    public function update(Request $request, CrmEmail $crmEmail)
    {
        $data = $request->validate([
            'folder' => 'nullable|in:INBOX,SENT,TRASH',
            'read_at' => 'nullable|date',
        ]);
        $crmEmail->update($data);
        return $crmEmail;
    }

    public function destroy(CrmEmail $crmEmail)
    {
        $crmEmail->delete();
        return response()->noContent();
    }

    private function resolveUserId($value): ?int
    {
        if (!$value) return null;
        $query = User::query();
        if (is_numeric($value)) {
            $query->where('id', (int) $value)->orWhere('keycloak_id', (string) $value);
        } else {
            $query->where('keycloak_id', (string) $value);
        }
        return $query->first()?->id;
    }
}
