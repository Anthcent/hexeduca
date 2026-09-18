<?php

namespace App\Console\Commands;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Console\Command;

class StorageBucketBootstrap extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:bucket-bootstrap';

    /**
     * The console command description.
     */
    protected $description = 'Idempotently create the configured S3/MinIO bucket if it does not already exist.';

    public function handle(): int
    {
        $config = config('filesystems.disks.s3');
        $bucket = $config['bucket'] ?? null;

        if (empty($bucket)) {
            $this->error('No bucket configured under filesystems.disks.s3.bucket (AWS_BUCKET).');

            return self::FAILURE;
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'credentials' => [
                'key' => $config['key'] ?? '',
                'secret' => $config['secret'] ?? '',
            ],
        ]);

        try {
            $client->headBucket(['Bucket' => $bucket]);
            $this->info("Bucket \"{$bucket}\" already exists — no action taken.");

            return self::SUCCESS;
        } catch (S3Exception $e) {
            $statusCode = $e->getStatusCode();

            if ($statusCode !== 404) {
                $this->error("Failed to check bucket \"{$bucket}\": unexpected status ({$statusCode}).");

                return self::FAILURE;
            }
        }

        try {
            $client->createBucket(['Bucket' => $bucket]);
            $this->info("Bucket \"{$bucket}\" created.");

            return self::SUCCESS;
        } catch (S3Exception $e) {
            $this->error("Failed to create bucket \"{$bucket}\": {$e->getAwsErrorCode()}");

            return self::FAILURE;
        }
    }
}
