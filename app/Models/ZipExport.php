<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZipExport extends Model
{
    //

    protected $fillable = [
        'zip_filename',
        'gcs_path',
        'status',
        'error_message',
        'start_date',
        'end_date',
    ];
}
