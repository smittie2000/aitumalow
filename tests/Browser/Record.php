<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property string $kind
 * @property string $name
 * @property string $status
 * @property array<string, mixed> $data
 */
#[Table(name: 'browser_records')]
final class Record extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
