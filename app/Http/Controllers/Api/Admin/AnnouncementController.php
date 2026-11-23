<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Announcement::orderByDesc('id')->get()->map(fn (Announcement $a) => $a->toApi());

        return response()->json(['data' => $data]);
    }

    public function store(AnnouncementRequest $request): JsonResponse
    {
        $published = (bool) $request->validated('is_published', false);

        $announcement = Announcement::create([
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'is_published' => $published,
            'published_at' => $published ? now() : null,
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::record($request->user()->id, 'announcement.create', 'announcement', $announcement->id, null, [
            'title' => $announcement->title,
            'is_published' => $announcement->is_published,
        ]);

        return response()->json(['data' => $announcement->toApi()], 201);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $old = ['title' => $announcement->title, 'is_published' => $announcement->is_published];
        $attrs = $request->validated();

        // published_at = waktu pertama kali diterbitkan.
        if (($attrs['is_published'] ?? false) && $announcement->published_at === null) {
            $attrs['published_at'] = now();
        }

        $announcement->update($attrs);

        AuditLogger::record($request->user()->id, 'announcement.update', 'announcement', $announcement->id, $old, [
            'title' => $announcement->title,
            'is_published' => $announcement->is_published,
        ]);

        return response()->json(['data' => $announcement->toApi()]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $old = ['title' => $announcement->title, 'is_published' => $announcement->is_published];
        $announcement->delete();

        AuditLogger::record($request->user()->id, 'announcement.delete', 'announcement', $announcement->id, $old, null);

        return response()->json(['message' => 'Pengumuman dihapus.']);
    }
}
