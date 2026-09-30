<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $status
 * @property string $owner_name
 * @property string $owner_email
 */
#[Table(name: 'browser_leads')]
final class Lead extends Model
{
    protected $guarded = [];
}
