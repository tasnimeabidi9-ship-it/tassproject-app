<?php

namespace App\Http\Controllers;

use App\Models\Sequence;
use Illuminate\Http\Request;

class SequenceController extends Controller
{
    public function index()
    {
        return Sequence::orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        return response()->json(Sequence::create($data), 201);
    }

    public function update(Request $request, Sequence $sequence)
    {
        $data = $this->validated($request);
        $sequence->update($data);
        return response()->json($sequence);
    }

    public function destroy(Sequence $sequence)
    {
        $sequence->delete();
        return response()->json(['message' => 'Sequence deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.channel' => 'required|in:email,sms',
            'steps.*.delay_days' => 'required|integer|min:0',
            'steps.*.subject' => 'nullable|string|max:255',
            'steps.*.body' => 'required|string',
        ]);
    }
}
