<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * GET /api/tasks
     * Query params: status, assigned_to (defaults to current user for non-admins)
     */
    public function index(Request $request)
    {
        $query = Task::with('contact:id,name,email,company', 'assignedTo:id,name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->user()->role !== 'admin') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($assignedTo = $request->query('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        return $query->orderByRaw('(due_date IS NULL)')->orderBy('due_date')->get();
    }

    /**
     * POST /api/tasks
     * Body: { contact_id, title, description?, due_date?, assigned_to? }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $task = Task::create([
            'contact_id' => $data['contact_id'],
            'assigned_to' => $data['assigned_to'] ?? $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'todo',
            'created_by_ai' => false,
        ]);

        return response()->json($task->load('contact:id,name,email,company', 'assignedTo:id,name'), 201);
    }

    /**
     * PATCH /api/tasks/{task}
     * Body: { status?, title?, description?, due_date?, assigned_to? }
     */
    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'status' => 'nullable|in:todo,in_progress,done',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $task->update(array_filter($data, fn ($v) => !is_null($v)));

        return response()->json($task->load('contact:id,name,email,company', 'assignedTo:id,name'));
    }

    /**
     * DELETE /api/tasks/{task}
     */
    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * Called by ReplyController when a reply is positive/interested —
     * auto-creates a follow-up task for the assigned rep.
     */
    public static function autoCreateFromReply(Contact $contact, string $sentiment): ?Task
    {
        if (!in_array($sentiment, ['Positive', 'Interested'], true)) {
            return null;
        }

        return Task::create([
            'contact_id' => $contact->id,
            'assigned_to' => $contact->assigned_to,
            'title' => 'Follow up with ' . ($contact->name ?? 'lead'),
            'description' => "Lead replied with sentiment \"{$sentiment}\". Review the conversation and schedule a call.",
            'due_date' => now()->addDay(),
            'status' => 'todo',
            'created_by_ai' => true,
        ]);
    }
}
