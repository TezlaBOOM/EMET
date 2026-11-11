<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    protected $table = 'mcp_servers';

    protected $fillable = [
        'name',
        'slug',
        'transport',
        'endpoint_url',
        'command',
        'auth_token',
        'status',
        'discovered_tools',
        'synced_skills_count',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auth_token' => 'encrypted',
            'discovered_tools' => 'array',
            'synced_skills_count' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }
}

