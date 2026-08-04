<?php

namespace App\Http\Controllers;

use App\Models\TargetProfile;
use Illuminate\Http\Request;

class TargetProfileController extends Controller
{
    public function index()
    {
        return TargetProfile::orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
        ]);

        $data['created_by'] = $request->user()->id;

        $profile = TargetProfile::create($data);

        return response()->json($profile, 201);
    }

    public function update(Request $request, TargetProfile $profile)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
        ]);

        $profile->update($data);

        return response()->json($profile);
    }

    public function destroy(TargetProfile $profile)
    {
        $profile->delete();

        return response()->json(['message' => 'Profile deleted.']);
    }
}
