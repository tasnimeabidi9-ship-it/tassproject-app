<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    /**
     * GET /api/contacts
     * Query params: search, status, stage, source, min_score, assigned_to,
     *               last_activity_after, sort (default -created_at), per_page
     */
    public function index(Request $request)
    {
        $query = Contact::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($stage = $request->query('stage')) {
            $query->where('stage', $stage);
        }

        if ($source = $request->query('source')) {
            $query->where('source_url', 'like', "%{$source}%");
        }

        if ($minScore = $request->query('min_score')) {
            $query->where('match_score', '>=', (int) $minScore);
        }

        if ($assignedTo = $request->query('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($after = $request->query('last_activity_after')) {
            $query->where('updated_at', '>=', $after);
        }

        $query->orderBy('updated_at', 'desc');

        $perPage = (int) $request->query('per_page', 25);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 25;

        return $query->paginate($perPage);
    }

    /**
     * GET /api/contacts/{id}
     * Full profile including activity timeline.
     */
    public function show(Contact $contact)
    {
        $contact->load(['assignedTo:id,name,email']);

        $messages = $contact->messages()->get()->map(fn ($m) => [
            'type' => 'message',
            'timestamp' => $m->sent_at ?? $m->created_at,
            'data' => $m,
        ]);

        $replies = $contact->replies()->get()->map(fn ($r) => [
            'type' => 'reply',
            'timestamp' => $r->created_at,
            'data' => $r,
        ]);

        $tasks = $contact->tasks()->get()->map(fn ($t) => [
            'type' => 'task',
            'timestamp' => $t->created_at,
            'data' => $t,
        ]);

        $timeline = $messages->concat($replies)->concat($tasks)
            ->sortByDesc('timestamp')
            ->values();

        return response()->json([
            'contact' => $contact,
            'timeline' => $timeline,
        ]);
    }

    /**
     * POST /api/contacts
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $contact = Contact::create($data);

        return response()->json($contact, 201);
    }

    /**
     * PATCH /api/contacts/{id}
     */
    public function update(Request $request, Contact $contact)
    {
        $data = $this->validated($request, $contact->id);
        $contact->update($data);

        return response()->json($contact);
    }

    /**
     * DELETE /api/contacts/{id}
     */
    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->json(['message' => 'Contact deleted.']);
    }

    /**
     * PATCH /api/contacts/{id}/stage
     * Body: { stage }
     * Dedicated lightweight endpoint for Kanban drag-and-drop.
     */
    public function updateStage(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'stage' => 'required|in:' . implode(',', Contact::STAGES),
        ]);

        $contact->update(['stage' => $validated['stage']]);

        return response()->json($contact);
    }

    /**
     * POST /api/contacts/import
     * Multipart form with a 'file' field (CSV: name,email,phone,company,website,source_url).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $allowed = ['name', 'email', 'phone', 'company', 'website', 'domain', 'source_url'];
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            $record = array_combine($header, $line);
            $record = array_intersect_key($record, array_flip($allowed));

            $validator = Validator::make($record, [
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'company' => 'nullable|string|max:255',
                'website' => 'nullable|string|max:255',
                'domain' => 'nullable|string|max:255',
                'source_url' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[] = "Row {$row}: " . implode('; ', $validator->errors()->all());
                continue;
            }

            if (empty($record['email']) && empty($record['name'])) {
                $skipped++;
                $errors[] = "Row {$row}: missing both name and email.";
                continue;
            }

            // Dedup by email
            if (!empty($record['email']) && Contact::where('email', $record['email'])->exists()) {
                $skipped++;
                $errors[] = "Row {$row}: email already exists, skipped.";
                continue;
            }

            Contact::create(array_merge($record, [
                'stage' => 'lead_found',
                'status' => 'active',
            ]));
            $imported++;
        }

        fclose($handle);

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * GET /api/contacts/export
     * Streams a CSV of all contacts matching the same filters as index().
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Contact::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($stage = $request->query('stage')) {
            $query->where('stage', $stage);
        }

        $columns = ['id', 'name', 'email', 'phone', 'company', 'website', 'domain', 'source_url', 'match_score', 'stage', 'status', 'created_at'];

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            $query->orderBy('id')->chunk(500, function ($contacts) use ($out, $columns) {
                foreach ($contacts as $contact) {
                    fputcsv($out, array_map(fn ($col) => $contact->{$col}, $columns));
                }
            });

            fclose($out);
        }, 'contacts_export_' . now()->format('Y-m-d_His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'domain' => 'nullable|string|max:255',
            'source_url' => 'nullable|string|max:255',
            'match_score' => 'nullable|integer|min:0|max:100',
            'stage' => 'nullable|in:' . implode(',', Contact::STAGES),
            'status' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);
    }
}
