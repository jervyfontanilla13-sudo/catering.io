<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        foreach ($public->allFiles('service-contracts') as $path) {
            if ($private->exists($path)) {
                $publicHash = hash_file('sha256', $public->path($path));
                $privateHash = hash_file('sha256', $private->path($path));
                if ($publicHash === false || $privateHash === false || ! hash_equals($publicHash, $privateHash)) {
                    throw new RuntimeException("A conflicting private contract file already exists for {$path}.");
                }
            } else {
                $stream = $public->readStream($path);
                if ($stream === false) {
                    throw new RuntimeException("Unable to read legacy contract file {$path}.");
                }

                try {
                    if (! $private->writeStream($path, $stream)) {
                        throw new RuntimeException("Unable to move legacy contract file {$path} to private storage.");
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            if (! $public->delete($path)) {
                throw new RuntimeException("Unable to remove public contract file {$path} after migration.");
            }
        }
    }

    public function down(): void
    {
        // Private contract files stay private when rolling back schema migrations.
    }
};
