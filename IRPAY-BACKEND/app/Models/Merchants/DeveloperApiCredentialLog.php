<?php

namespace App\Models\Merchants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperApiCredentialLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'developer_api_credential_id' => 'integer',
        'action' => 'string',
        'meta' => 'array',
        'performed_by_type' => 'string',
        'performed_by_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(DeveloperApiCredential::class, 'developer_api_credential_id');
    }
}

