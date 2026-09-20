<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash)
    {
        if (Auth::check() && Auth::id() !== $id) abort(403);
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        if (!$user->hasVerifiedEmail() && $user->markEmailAsVerified()) event(new Verified($user));
        if (Auth::check()) Auth::user()->refresh();
        return redirect('/app/verify-email?verified=1');
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) return response()->json(['message' => 'Email already verified.', 'verified' => true]);
        $user->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification email sent.', 'verified' => false]);
    }
}
