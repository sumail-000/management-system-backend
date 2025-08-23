<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    // GET /api/admin/support/tickets
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->get('per_page', 25), 100));
        $query = SupportTicket::query()
            ->with(['user:id,name,email'])
            ->withCount('messages');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%$search%")
                  ->orWhere('subject', 'like', "%$search%");
            });
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }
        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        $query->orderByDesc('updated_at');
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ]
        ]);
    }

    // GET /api/admin/support/tickets/{id}
    public function show($id)
    {
        $ticket = SupportTicket::with(['user:id,name,email'])
            ->findOrFail($id);

        $messages = SupportMessage::where('ticket_id', $ticket->id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($m) {
                $author = $m->is_admin
                    ? [
                        'type' => 'admin',
                        'id' => $m->admin_id,
                    ] : [
                        'type' => 'user',
                        'id' => $m->user_id,
                    ];
                return [
                    'id' => $m->id,
                    'message' => $m->message,
                    'is_admin' => (bool) $m->is_admin,
                    'author' => $author,
                    'created_at' => $m->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'category' => $ticket->category,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'last_reply_at' => $ticket->last_reply_at,
                    'created_at' => $ticket->created_at,
                    'user' => $ticket->user,
                ],
                'messages' => $messages,
            ]
        ]);
    }

    // POST /api/admin/support/tickets/{id}/reply
    public function reply(Request $request, $id)
    {
        $data = $request->validate([
            'message' => 'required|string',
            'status' => 'nullable|in:open,pending,resolved,closed',
        ]);

        return DB::transaction(function () use ($id, $data) {
            $ticket = SupportTicket::lockForUpdate()->findOrFail($id);
            $adminId = auth('admin')->id();

            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'admin_id' => $adminId,
                'is_admin' => true,
                'message' => $data['message'],
            ]);

            $ticket->last_reply_at = now();
            if (!empty($data['status'])) {
            $ticket->status = $data['status'];
            }
            $ticket->save();
            
            // Create notification for the ticket owner
            try {
            Notification::create([
            'user_id' => $ticket->user_id,
            'type' => 'support_reply',
            'title' => 'New reply to your ticket ' . ($ticket->ticket_number ?? '#'.$ticket->id),
            'message' => mb_strimwidth($data['message'] ?? 'You have a new reply from support.', 0, 200, '...'),
            'metadata' => [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            ],
            'link' => '/support/tickets/' . $ticket->id,
            ]);
            } catch (\Throwable $e) {
            // swallow notification errors to not block reply
            }
            
            return response()->json([
            'success' => true,
            'message' => 'Reply sent',
            ]);
        });
    }

    // PATCH /api/admin/support/tickets/{id}/status
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:open,pending,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $data['status'];
        $ticket->save();

        // Notify user about status change
        try {
            Notification::create([
                'user_id' => $ticket->user_id,
                'type' => 'support_status',
                'title' => 'Your ticket status was updated',
                'message' => 'Ticket ' . ($ticket->ticket_number ?? '#'.$ticket->id) . ' is now ' . $ticket->status . '.',
                'metadata' => [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status,
                ],
                'link' => '/support/tickets/' . $ticket->id,
            ]);
        } catch (\Throwable $e) {
            // ignore notification errors
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
        ]);
    }
}
