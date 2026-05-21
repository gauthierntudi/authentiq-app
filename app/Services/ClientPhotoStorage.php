<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Http\UploadedFile;

class ClientPhotoStorage
{
    public function store(Client $client, UploadedFile $file): string
    {
        $dir = public_path('uploads/clients');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'client_'.$client->id_client.'_'.uniqid().'.jpg';
        $file->move($dir, $name);

        return 'uploads/clients/'.$name;
    }
}
