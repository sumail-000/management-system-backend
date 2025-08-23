<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;

class SupportController extends Controller
{
    // List user's tickets (recent)
    public function listTickets(Request $request)
    {
        $tickets = SupportTicket::where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->limit($request->integer('limit', 25))
            ->get(['id','ticket_number','subject','category','priority','status','last_reply_at','created_at']);
        return response()->json(['success' => true, 'data' => $tickets]);
    }

    // Start a ticket (temporary) — no number assigned yet
    public function startTicket(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|max:50',
            'priority' => 'required|string|in:low,medium,high,urgent',
        ]);

        // Create a temporary ticket row (no ticket_number yet)
        $ticket = new SupportTicket();
        $ticket->user_id = Auth::id();
        $ticket->category = $data['category'];
        $ticket->priority = $data['priority'];
        $ticket->subject = ''; // will be filled on finalize
        $ticket->status = 'open';
        $ticket->ticket_number = 'PENDING';
        $ticket->save();

        return response()->json(['success' => true, 'data' => [ 'id' => $ticket->id ]], 201);
    }

    // Finalize ticket: assign ticket_number TK-YYYY-XXX, update subject, create first message
    public function finalizeTicket(Request $request, $id)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        return DB::transaction(function () use ($id, $data) {
            $ticket = SupportTicket::where('id', $id)->where('user_id', Auth::id())->lockForUpdate()->firstOrFail();

            // Assign ticket number only now (to avoid gaps if user cancels)
            if ($ticket->ticket_number === 'PENDING') {
                $ticket->ticket_number = SupportTicket::generateTicketNumber();
            }
            $ticket->subject = $data['subject'];
            $ticket->last_reply_at = now();
            $ticket->save();

            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'is_admin' => false,
                'message' => $data['message'],
            ]);

            // Create a user-friendly notification for ticket creation
            try {
                Notification::create([
                    'user_id' => $ticket->user_id,
                    'type' => 'support_ticket_created',
                    'title' => 'Support ticket created',
                    'message' => 'Your ticket ' . ($ticket->ticket_number ?? '#' . $ticket->id) . ' has been created. Our support team will get back to you soon.',
                    'metadata' => [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'category' => $ticket->category,
                    ],
                    'link' => '/support/tickets/' . $ticket->id,
                ]);
            } catch (\Throwable $e) {
                // Do not block ticket creation on notification errors
            }

            return response()->json(['success' => true, 'data' => $ticket->fresh()], 201);
        });
    }

    // Cancel a temporary ticket (revert and delete if not finalized)
    public function cancelTicket($id)
    {
        $ticket = SupportTicket::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        if ($ticket->ticket_number !== 'PENDING') {
            return response()->json(['success' => false, 'message' => 'Ticket already finalized'], 400);
        }
        $ticket->delete();
        return response()->json(['success' => true]);
    }

    // List FAQs
    public function listFaqs()
    {
        $faqs = Faq::orderBy('id')->get(['id','question','answer','category']);
        return response()->json(['success' => true, 'data' => $faqs]);
    }

    // Get a ticket detail with messages (user scope)
    public function getTicket($id)
    {
        $ticket = SupportTicket::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $messages = SupportMessage::where('ticket_id', $ticket->id)
            ->orderBy('created_at')
            ->get(['id','message','is_admin','user_id','admin_id','created_at']);
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
                ],
                'messages' => $messages,
            ]
        ]);
    }

    // Add a user message to a ticket
    public function addMessage(Request $request, $id)
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        return DB::transaction(function () use ($id, $data) {
            $ticket = SupportTicket::where('id', $id)->where('user_id', Auth::id())->lockForUpdate()->firstOrFail();

            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'is_admin' => false,
                'message' => $data['message'],
            ]);

            $ticket->last_reply_at = now();
            $ticket->save();

            return response()->json(['success' => true, 'message' => 'Message added']);
        });
    }
}
