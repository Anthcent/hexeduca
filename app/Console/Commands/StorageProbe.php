<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StorageProbe extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:probe';

    /**
     * The console command description.
     */
    protected $description = 'Upload, read back, and delete a temporary object on the s3 disk to prove it is functional. Never prints credential values.';

    public function handle(): int
    {
        $disk = Storage::disk('s3');
        $key = 'storage-probe/'.Str::uuid()->toString().'.txt';
        $contents = 'storage-probe-'.Str::random(16);

        try {
            $disk->put($key, $contents);

            $readBack = $disk->get($key);

            if ($readBack !== $contents) {
                $this->error('FAIL: content mismatch on read-back.');

                return self::FAILURE;
            }

            $disk->delete($key);

            if ($disk->exists($key)) {
                $this->error('FAIL: object still present after delete.');

                return self::FAILURE;
            }

            $this->info('PASS: upload, read-back, and delete all succeeded on the s3 disk.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Deliberately do not include $e->getMessage() verbatim — SDK
            // exceptions can embed request details. Report only the class.
            $this->error('FAIL: '.class_basename($e));

            return self::FAILURE;
        }
    }
}
