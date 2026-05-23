<?php

namespace App\Services\Aws;

use Aws\Rekognition\RekognitionClient;
use Aws\Textract\TextractClient;

class AwsClientFactory
{
    public static function configured(): bool
    {
        return (bool) config('services.ses.key') && (bool) config('services.ses.secret');
    }

    public static function region(): string
    {
        return (string) config('authentiq.aws_region', config('services.ses.region', 'us-east-1'));
    }

    /** @return array<string, mixed> */
    public static function credentialsArray(): array
    {
        return [
            'version' => 'latest',
            'region' => self::region(),
            'credentials' => [
                'key' => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function credentials(): array
    {
        return self::credentialsArray();
    }

    public static function textract(): TextractClient
    {
        return new TextractClient(self::credentials());
    }

    public static function rekognition(): RekognitionClient
    {
        return new RekognitionClient(self::credentials());
    }
}
