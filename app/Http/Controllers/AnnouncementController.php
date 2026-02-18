<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        // For the Dashboard Feed (Active only, targeted to user)
        $user = Auth::user();

        // Map common role names if necessary.
        // Assuming $user->role->name returns 'SALES', 'MANAGER', etc. matching the stored JSON.
        // If specific logic is needed to get the role string:
        $roleName = $user->role->name ?? 'SALES'; // Default fallback or fetch from relation

        // Correction: Using the scopeForUser might be tricky if we don't know exact role string.
        // Let's be manual here for safety.

        $query = Announcement::active()
            ->with('author:id,name')
            ->latest();

        if ($user->role_id != 1 && $roleName !== 'ADMIN' && $roleName !== 'Super Admin') {
            // Admins see everything? Or just their own feed?
            // Prompt: "Aktywne komunikaty muszą pojawiać się... dla odpowiednich ról"
            // So Admin also sees announcements targeted to ADMIN.

            $query->where(function($q) use ($roleName) {
                $q->whereJsonContains('target_roles', $roleName)
                  ->orWhereJsonContains('target_roles', 'ALL');
            });
        }

        if ($user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        return response()->json($query->limit(10)->get());
    }

    public function manage(Request $request)
    {
        // For the Super Admin History List (All items)
        $query = Announcement::with('author:id,name')->latest();

        if ($request->user()->organization_id) {
            $query->where('organization_id', $request->user()->organization_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string',
            'target_roles' => 'required', // Can be array or JSON string
            'expires_at' => 'nullable|date',
            'image' => 'nullable|image|max:10240', // Max 10MB
            'attachment' => 'nullable|file|max:20480', // Max 20MB
        ]);

        $targetRoles = $request->input('target_roles');
        if (is_string($targetRoles)) {
            $decoded = json_decode($targetRoles, true);
            if (is_array($decoded)) {
                $targetRoles = $decoded;
            }
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('announcements/images', 'public');
            $imageUrl = '/storage/' . $path;
        }

        $attachmentUrl = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $path = $file->store('announcements/attachments', 'public');
            $attachmentUrl = '/storage/' . $path;
        }

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'target_roles' => $targetRoles,
            'expires_at' => $validated['expires_at'] ?? null,
            'author_id' => Auth::id(),
            'organization_id' => Auth::user()->organization_id ?? null,
            'image_url' => $imageUrl,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachmentName,
        ]);

        return response()->json($announcement, 201);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'category' => 'sometimes|string',
            'target_roles' => 'nullable', // JSON string or array
            'expires_at' => 'nullable|date',
            'image' => 'nullable|image|max:10240', // Max 10MB
            'attachment' => 'nullable|file|max:20480', // Max 20MB
        ]);

        // Handle JSON encoding/decoding for target_roles if present
        if ($request->has('target_roles')) {
            $targetRoles = $request->input('target_roles');
            if (is_string($targetRoles)) {
                $decoded = json_decode($targetRoles, true);
                if (is_array($decoded)) {
                    $validated['target_roles'] = $decoded;
                }
            }
        }

        // Handle Image Upload
        if ($request->hasFile('image')) {
            // Delete old if exists? (Optional, good practice)
            // Storage::disk('public')->delete(str_replace('/storage/', '', $announcement->image_url));

            $path = $request->file('image')->store('announcements/images', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        // Handle Attachment Upload
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('announcements/attachments', 'public');
            $validated['attachment_url'] = '/storage/' . $path;
            $validated['attachment_name'] = $file->getClientOriginalName();
        }

        $announcement->update($validated);

        return response()->json($announcement);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return response()->noContent();
    }
}
