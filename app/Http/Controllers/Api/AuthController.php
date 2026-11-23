<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\VoterLoginRequest;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\User;
use App\Models\VoteParticipation;
use App\Models\Voter;
use App\Support\AuditLogger;
use App\Support\VoterIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AuthController extends Controller
{
    public function voterLogin(VoterLoginRequest $request): JsonResponse
    {
        // Urutan pemeriksaan (bagian 6.4): jadwal -> terdaftar -> belum memilih.
        $election = Election::current();
        if (! $election) {
            throw new ApiException('Belum ada pemilihan yang dijadwalkan.', 403, 'no_election');
        }

        $status = $election->status();
        if ($status === 'belum_dimulai') {
            throw new ApiException('Pemilihan belum dibuka.', 403, 'election_not_started');
        }
        if ($status !== 'berlangsung') {
            throw new ApiException('Pemilihan telah berakhir.', 403, 'election_ended');
        }

        $type = $request->validated('type');
        $identifier = $request->validated('identifier');

        $voter = $this->findVoter($type, $identifier);
        if (! $voter) {
            $message = $type === 'siswa'
                ? 'NIS tidak ditemukan. Periksa kembali NIS Anda atau tanyakan ke panitia.'
                : 'Nama tidak ditemukan. Tulis nama lengkap sesuai daftar.';
            throw new ApiException($message, 401, 'voter_not_found');
        }

        $registered = ElectionVoter::where('election_id', $election->id)
            ->where('voter_id', $voter->id)
            ->exists();
        if (! $registered) {
            throw new ApiException('Anda tidak terdaftar sebagai pemilih pada pemilihan ini.', 403, 'not_registered');
        }

        $alreadyVoted = VoteParticipation::where('election_id', $election->id)
            ->where('voter_id', $voter->id)
            ->exists();
        if ($alreadyVoted) {
            throw new ApiException('Anda sudah menggunakan hak pilih.', 409, 'already_voted');
        }

        // Satu sesi aktif per pemilih; token berumur pendek.
        $voter->tokens()->delete();
        $token = $voter->createToken('voter', ['voter'], now()->addMinutes(30));

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'voter' => $this->voterPayload($voter),
            'election' => $election->toApi(),
        ]]);
    }

    public function adminLogin(AdminLoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->validated('username'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw new ApiException('Username atau password salah.', 401, 'invalid_credentials');
        }

        $token = $user->createToken('admin', ['admin'], now()->addHours(12));
        AuditLogger::record($user->id, 'admin.login', 'user', $user->id);

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'user' => ['name' => $user->name, 'username' => $user->username, 'role' => 'admin'],
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            return response()->json(['data' => [
                'role' => 'admin',
                'name' => $user->name,
                'username' => $user->username,
            ]]);
        }

        return response()->json(['data' => ['role' => 'pemilih'] + $this->voterPayload($user)]);
    }

    private function findVoter(string $type, string $identifier): ?Voter
    {
        $query = Voter::where('type', $type)->where('is_active', true);

        try {
            if ($type === 'siswa') {
                return $query->where('nis_hash', VoterIdentity::hashNis($identifier))->first();
            }

            $key = VoterIdentity::normalizeName($identifier);

            return $key === '' ? null : $query->where('login_key', $key)->first();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function voterPayload(Voter $voter): array
    {
        return [
            'name' => $voter->name,
            'type' => $voter->type,
            'class_name' => $voter->class_name,
            'unit' => $voter->unit,
        ];
    }
}
