<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\User;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\Response;

class BackupController extends Controller
{
    /**
     * GET /api/backup/files?u=<user>&p=<pass>&project=stock&driver=public|public_path
     *
     * Returns: { status, project, count, data: [ "assets/images/1.jpg", ... ] }
     */
    public function listFiles(Request $request)
    {
        // 1) Validate
        $request->validate([
            'u' => 'required|string',
            'p' => 'required|string',
        ]);

        $username = $request->input('u');
        $password = $request->input('p');

        // 2) Authenticate
        $user = User::where('mobile', $username)->orWhere('name', $username)->first();
        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/';
        $urls = [];

        // =============== 3. STEP 1: Create DB Backup ===============
        $dbBackupUrl = null;
        try {
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbPort = env('DB_PORT', '3306');
            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPass = env('DB_PASSWORD');

            if ($dbName && $dbUser) {
                $backupDir = public_path('dbbackup');
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }

                $filename = 'db_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backupDir . '/' . $filename;

                $command = sprintf(
                    'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
                    escapeshellarg($dbHost),
                    escapeshellarg($dbPort),
                    escapeshellarg($dbUser),
                    escapeshellarg($dbPass),
                    escapeshellarg($dbName),
                    escapeshellarg($filepath)
                );

                $process = Process::fromShellCommandline($command);
                $process->setTimeout(3600);
                $process->run();

                if ($process->isSuccessful() && file_exists($filepath)) {
                    $dbBackupUrl = $baseUrl . 'dbbackup/' . $filename;
                }
            }
        } catch (\Exception $e) {
            // DB backup fail → ignore, continue with file list
        }

        // =============== 4. STEP 2: List All Public Files ===============
        $publicPath = public_path();
        if (is_dir($publicPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($publicPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = ltrim(str_replace($publicPath, '', str_replace('\\', '/', $file->getPathname())), '/');
                    $urls[] = $baseUrl . $relativePath;
                }
            }
        }

        // =============== 5. STEP 3: Add DB Backup URL (if created) ===============
        if ($dbBackupUrl) {
            // Insert at top (latest backup first)
            array_unshift($urls, $dbBackupUrl);
        }

        // =============== 6. Final Response ===============
        return response()->json([
            'status' => true,
            'count' => count($urls),
            'data' => $urls,
        ]);
    }
}
